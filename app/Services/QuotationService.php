<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\ProjectStatus;
use App\Enums\QuotationStatus;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\InvoiceNumber;
use App\Support\QuotationCalculator;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Alur quotation: draf -> terkirim -> disetujui/ditolak (Bagian 5, Fase A).
 *
 * Otorisasi (Policy) dicek di komponen; service ini menjaga invarian bisnis di dalam transaksi
 * dan melempar DomainException berisi kunci terjemahan bila aturan dilanggar.
 */
class QuotationService
{
    /** Hari jatuh tempo Invoice DP sejak deal. */
    public const DP_DUE_DAYS = 7;

    /**
     * Simpan draf (baru bila $quotation null, versi = versi terakhir + 1).
     *
     * @param  array{operational_cost: int, margin_percent: string, dp_percent: int, valid_until: ?string}  $header
     * @param  list<array{title: string, description: ?string, hours: string, rate: int}>  $items  rate dalam satuan terkecil
     */
    public function saveDraft(Project $project, ?Quotation $quotation, array $header, array $items): Quotation
    {
        $this->assertNegotiable($project);

        if ($items === []) {
            throw new DomainException('quotations.errors.empty');
        }

        return DB::transaction(function () use ($project, $quotation, $header, $items) {
            $calc = QuotationCalculator::calculate(
                $project->currency,
                array_map(fn (array $i) => ['hours' => $i['hours'], 'rate' => $i['rate']], $items),
                $header['operational_cost'],
                $header['margin_percent'],
            );

            $attributes = [
                'currency' => $project->currency,
                'subtotal' => $calc['subtotal'],
                'operational_cost' => $calc['operational'],
                'margin_percent' => $header['margin_percent'],
                'dp_percent' => $header['dp_percent'],
                'total' => $calc['total'],
                'valid_until' => $header['valid_until'],
            ];

            if ($quotation === null) {
                $version = (int) Quotation::query()->where('project_id', $project->id)->max('version') + 1;

                $quotation = new Quotation($attributes + ['project_id' => $project->id, 'version' => $version]);
                $quotation->status = QuotationStatus::Draft;
                $quotation->save();
            } else {
                $quotation = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);

                if ($quotation->status !== QuotationStatus::Draft) {
                    throw new DomainException('quotations.errors.not_draft');
                }

                $quotation->update($attributes);
                $quotation->items()->delete();
            }

            foreach ($items as $position => $item) {
                $quotation->items()->create([
                    'title' => $item['title'],
                    'description' => $item['description'],
                    'hours' => $item['hours'],
                    'rate' => $item['rate'],
                    'amount' => $calc['amounts'][$position],
                    'sort_order' => $position,
                ]);
            }

            return $quotation->load('items');
        });
    }

    /** Draf baru sebagai salinan versi sebelumnya; bila sudah ada draf, draf itu yang dipakai. */
    public function newVersionFrom(Quotation $source): Quotation
    {
        return DB::transaction(function () use ($source) {
            $project = Project::query()->lockForUpdate()->findOrFail($source->project_id);
            $this->assertNegotiable($project);

            $existing = Quotation::query()
                ->where('project_id', $project->id)
                ->where('status', QuotationStatus::Draft->value)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $copy = new Quotation([
                'project_id' => $project->id,
                'version' => (int) Quotation::query()->where('project_id', $project->id)->max('version') + 1,
                'currency' => $project->currency,
                'subtotal' => $source->subtotal,
                'operational_cost' => $source->operational_cost,
                'margin_percent' => $source->margin_percent,
                'dp_percent' => $source->dp_percent,
                'total' => $source->total,
                'valid_until' => $source->isExpired() ? null : $source->valid_until,
            ]);
            $copy->status = QuotationStatus::Draft;
            $copy->save();

            foreach ($source->items as $item) {
                $copy->items()->create($item->only(['title', 'description', 'hours', 'rate', 'amount', 'sort_order']));
            }

            return $copy;
        });
    }

    /** Admin mengirim draf ke klien. Proyek lead -> quoted; versi terkirim sebelumnya digantikan. */
    public function send(Quotation $quotation, User $actor): Quotation
    {
        return DB::transaction(function () use ($quotation, $actor) {
            $quotation = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
            $project = Project::query()->lockForUpdate()->findOrFail($quotation->project_id);

            if ($quotation->status !== QuotationStatus::Draft) {
                throw new DomainException('quotations.errors.not_draft');
            }

            $this->assertNegotiable($project);

            if ($quotation->total <= 0 || ! $quotation->items()->exists()) {
                throw new DomainException('quotations.errors.empty');
            }

            if ($quotation->isExpired()) {
                throw new DomainException('quotations.errors.expired');
            }

            Quotation::query()
                ->where('project_id', $project->id)
                ->where('status', QuotationStatus::Sent->value)
                ->whereKeyNot($quotation->id)
                ->get()
                ->each(function (Quotation $old) use ($project, $actor) {
                    $old->status = QuotationStatus::Rejected;
                    $old->save();

                    ActivityLogger::log(ActivityAction::QuotationRejected, $project, [
                        'version' => $old->version,
                        'reason' => 'superseded',
                    ], $actor);
                });

            $quotation->status = QuotationStatus::Sent;
            $quotation->sent_at = now();
            $quotation->save();

            if ($project->status === ProjectStatus::Lead) {
                $this->move($project, ProjectStatus::Quoted, $actor);
            }

            ActivityLogger::log(ActivityAction::QuotationSent, $project, [
                'version' => $quotation->version,
                'currency' => $quotation->currency->value,
                'total' => $quotation->total,
            ], $actor);

            return $quotation;
        });
    }

    /**
     * Klien menyetujui: harga disepakati (deal), tercatat dengan waktu dan IP, mata uang dikunci,
     * Invoice DP terbit, proyek menunggu DP.
     *
     * @return array{quotation: Quotation, invoice: Invoice}
     */
    public function accept(Quotation $quotation, User $client, ?string $ip): array
    {
        return DB::transaction(function () use ($quotation, $client, $ip) {
            $quotation = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
            $project = Project::query()->lockForUpdate()->findOrFail($quotation->project_id);

            if ($quotation->status !== QuotationStatus::Sent) {
                throw new DomainException('quotations.errors.not_sent');
            }

            if ($quotation->isExpired()) {
                throw new DomainException('quotations.errors.expired');
            }

            if (! in_array($project->status, [ProjectStatus::Quoted, ProjectStatus::Negotiating], true)) {
                throw new DomainException('quotations.errors.project_state');
            }

            $quotation->status = QuotationStatus::Accepted;
            $quotation->accepted_at = now();
            $quotation->accepted_ip = $ip;
            $quotation->save();

            // Mata uang dan harga final mengikuti quotation yang disetujui.
            $oldPrice = [
                'currency' => $project->currency->value,
                'budget_reference' => $project->budget_reference,
                'final_price' => $project->final_price,
            ];
            $project->currency = $quotation->currency;
            $project->final_price = $quotation->total;
            $project->save();

            ActivityLogger::log(ActivityAction::PriceChanged, $project, [
                'old' => $oldPrice,
                'new' => [
                    'currency' => $quotation->currency->value,
                    'budget_reference' => $project->budget_reference,
                    'final_price' => $quotation->total,
                ],
            ], $client);

            ActivityLogger::log(ActivityAction::QuotationAccepted, $project, [
                'version' => $quotation->version,
                'total' => $quotation->total,
                'ip' => $ip,
            ], $client);

            $this->move($project, ProjectStatus::Deal, $client);

            $invoice = $this->createDownPaymentInvoice($project, $quotation, $client);

            $this->move($project, ProjectStatus::AwaitingDp, $client);

            return ['quotation' => $quotation, 'invoice' => $invoice];
        });
    }

    /** Klien menolak; negosiasi lanjut lewat chat dan admin bisa mengirim versi baru. */
    public function reject(Quotation $quotation, User $client): Quotation
    {
        return DB::transaction(function () use ($quotation, $client) {
            $quotation = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);

            if ($quotation->status !== QuotationStatus::Sent) {
                throw new DomainException('quotations.errors.not_sent');
            }

            $quotation->status = QuotationStatus::Rejected;
            $quotation->save();

            ActivityLogger::log(ActivityAction::QuotationRejected, $quotation->project, [
                'version' => $quotation->version,
                'reason' => 'client',
            ], $client);

            return $quotation;
        });
    }

    private function createDownPaymentInvoice(Project $project, Quotation $quotation, User $actor): Invoice
    {
        $invoice = new Invoice([
            'project_id' => $project->id,
            'invoice_number' => InvoiceNumber::next(),
            'type' => InvoiceType::Dp,
            'currency' => $quotation->currency,
            'amount' => $quotation->dpMoney()->amount,
            'due_date' => now()->addDays(self::DP_DUE_DAYS)->toDateString(),
        ]);
        $invoice->status = InvoiceStatus::Unpaid;
        $invoice->save();

        ActivityLogger::log(ActivityAction::InvoiceCreated, $project, [
            'invoice_number' => $invoice->invoice_number,
            'type' => $invoice->type->value,
            'currency' => $invoice->currency->value,
            'amount' => $invoice->amount,
        ], $actor);

        return $invoice;
    }

    private function move(Project $project, ProjectStatus $to, User $actor): void
    {
        $from = $project->status;
        $project->transitionTo($to);

        ActivityLogger::changed(ActivityAction::StatusChanged, $project, $from, $to, $actor);
    }

    /** Quotation hanya boleh dibuat/diubah/dikirim selama harga belum disepakati. */
    private function assertNegotiable(Project $project): void
    {
        if ($project->isCurrencyLocked()) {
            throw new DomainException('quotations.errors.locked');
        }
    }
}
