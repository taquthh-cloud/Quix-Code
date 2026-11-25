<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\ProjectStatus;
use App\Enums\QuotationStatus;
use App\Events\DownPaymentInvoiceIssued;
use App\Events\QuotationExpired;
use App\Events\QuotationExpiringSoon;
use App\Events\QuotationSent;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Setting;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\InvoiceNumber;
use App\Support\PaymentTerms;
use App\Support\QuotationCalculator;
use App\Support\QuotationSnapshot;
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
     * @param  array{operational_cost: int, margin_percent: string, dp_percent: int, valid_until: ?string, discount_amount?: int, tax_bp?: int, payment_terms?: list<int>}  $header
     * @param  list<array{title: string, description: ?string, hours: string, rate: int}>  $items  rate dalam satuan terkecil
     */
    public function saveDraft(Project $project, ?Quotation $quotation, array $header, array $items): Quotation
    {
        $this->assertNegotiable($project);

        if ($items === []) {
            throw new DomainException('quotations.errors.empty');
        }

        $terms = $header['payment_terms'] ?? PaymentTerms::preset((int) $header['dp_percent']);

        if (! PaymentTerms::isValid($terms)) {
            throw new DomainException('quotations.errors.invalid_terms');
        }

        return DB::transaction(function () use ($project, $quotation, $header, $items, $terms) {
            $calc = QuotationCalculator::calculate(
                $project->currency,
                array_map(fn (array $i) => ['hours' => $i['hours'], 'rate' => $i['rate']], $items),
                $header['operational_cost'],
                $header['margin_percent'],
                (int) ($header['discount_amount'] ?? 0),
                (int) ($header['tax_bp'] ?? 0),
            );

            $attributes = [
                'currency' => $project->currency,
                'subtotal' => $calc['subtotal'],
                'operational_cost' => $calc['operational'],
                'margin_percent' => $header['margin_percent'],
                'margin_bp' => QuotationCalculator::percentToBp($header['margin_percent']),
                'discount_amount' => $calc['discount'],
                'tax_bp' => $calc['tax_bp'],
                'tax_amount' => $calc['tax'],
                'dp_percent' => intdiv($terms[0], 100),
                'payment_terms' => $terms,
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
                'margin_bp' => $source->margin_bp,
                'discount_amount' => $source->discount_amount,
                'tax_bp' => $source->tax_bp,
                'tax_amount' => $source->tax_amount,
                'dp_percent' => $source->dp_percent,
                'payment_terms' => $source->termBps(),
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
        $sent = DB::transaction(function () use ($quotation, $actor) {
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
                ->each(function (Quotation $old) use ($project, $quotation, $actor) {
                    $old->status = QuotationStatus::Superseded;
                    $old->save();

                    ActivityLogger::log(ActivityAction::QuotationSuperseded, $project, [
                        'version' => $old->version,
                        'superseded_by' => $quotation->version,
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

        // Setelah transaksi selesai: email tidak boleh terkirim untuk perubahan yang akhirnya di-rollback.
        QuotationSent::dispatch($sent);

        return $sent;
    }

    /**
     * Klien menyetujui: harga disepakati (deal), tercatat dengan waktu dan IP, mata uang dikunci,
     * Invoice DP terbit, proyek menunggu DP.
     *
     * @return array{quotation: Quotation, invoice: Invoice}
     */
    public function accept(Quotation $quotation, User $client, ?string $ip, ?string $userAgent = null): array
    {
        $result = DB::transaction(function () use ($quotation, $client, $ip, $userAgent) {
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
            $quotation->accepted_user_agent = $userAgent !== null ? mb_substr($userAgent, 0, 512) : null;
            $quotation->terms_version = $this->currentTermsVersion();
            $quotation->accepted_snapshot_hash = QuotationSnapshot::hash($quotation->unsetRelation('items'));
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
                'terms_version' => $quotation->terms_version,
                'snapshot_hash' => $quotation->accepted_snapshot_hash,
            ], $client);

            $this->move($project, ProjectStatus::Deal, $client);

            $invoice = $this->createDownPaymentInvoice($project, $quotation, $client);

            $this->move($project, ProjectStatus::AwaitingDp, $client);

            return ['quotation' => $quotation, 'invoice' => $invoice];
        });

        DownPaymentInvoiceIssued::dispatch($result['invoice']);

        return $result;
    }

    /** Teks S&K / kebijakan revisi dari Pengaturan untuk bahasa tertentu ('' bila belum diisi). */
    public function termsText(string $locale): string
    {
        $value = Setting::query()->where('key', 'quotation.terms.'.$locale)->value('value');

        if (is_string($value)) {
            $value = json_decode($value, true) ?? $value;
        }

        return trim((string) (is_array($value) ? ($value['value'] ?? '') : $value));
    }

    /** Versi S&K yang berlaku = sidik jari teks ID+EN; berubah bila teks di Pengaturan berubah. */
    public function currentTermsVersion(): string
    {
        $combined = $this->termsText('id')."\n".$this->termsText('en');

        return trim($combined) === '' ? 'none' : 'tc-'.substr(hash('sha256', $combined), 0, 12);
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

    /**
     * Scheduler harian (T3-06): tandai 'expired' semua quotation terkirim yang melewati valid_until.
     * Proyek tidak diubah: tetap quoted/negotiating, dan admin bisa mengirim versi baru.
     * Aman dijalankan berulang (idempoten) dan aman terhadap persetujuan klien yang bersamaan.
     *
     * @return int jumlah quotation yang baru ditandai kedaluwarsa
     */
    public function expireOverdue(): int
    {
        $ids = Quotation::query()
            ->where('status', QuotationStatus::Sent->value)
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', now()->toDateString())
            ->pluck('id');

        $count = 0;

        foreach ($ids as $id) {
            $expired = DB::transaction(function () use ($id): ?Quotation {
                // Baca ulang dengan kunci: klien mungkin baru saja menyetujui/menolak.
                $quotation = Quotation::query()->lockForUpdate()->find($id);

                if ($quotation === null || $quotation->status !== QuotationStatus::Sent || ! $quotation->isExpired()) {
                    return null;
                }

                $quotation->status = QuotationStatus::Expired;
                $quotation->expired_at = now();
                $quotation->save();

                ActivityLogger::log(ActivityAction::QuotationExpired, $quotation->project, [
                    'version' => $quotation->version,
                    'valid_until' => $quotation->valid_until?->format('Y-m-d'),
                ]);

                return $quotation;
            });

            if ($expired !== null) {
                $count++;
                QuotationExpired::dispatch($expired);
            }
        }

        return $count;
    }

    /**
     * Scheduler harian (T3-06): pengingat satu kali untuk quotation terkirim yang masa berlakunya
     * tinggal REMINDER_DAYS hari atau kurang (termasuk yang berakhir hari ini). Penanda reminded_at
     * mencegah pengingat ganda. Email pengingat dikirim oleh listener QuotationExpiringSoon (T3-09).
     *
     * @return int jumlah pengingat yang dicatat
     */
    public function remindExpiring(): int
    {
        $ids = Quotation::query()
            ->where('status', QuotationStatus::Sent->value)
            ->whereNull('reminded_at')
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '>=', now()->toDateString())
            ->whereDate('valid_until', '<=', now()->addDays(Quotation::REMINDER_DAYS)->toDateString())
            ->pluck('id');

        $count = 0;

        foreach ($ids as $id) {
            $reminded = DB::transaction(function () use ($id): ?Quotation {
                $quotation = Quotation::query()->lockForUpdate()->find($id);

                if ($quotation === null
                    || $quotation->status !== QuotationStatus::Sent
                    || $quotation->reminded_at !== null
                    || ! $quotation->isExpiringSoon()) {
                    return null;
                }

                $quotation->reminded_at = now();
                $quotation->save();

                ActivityLogger::log(ActivityAction::QuotationReminded, $quotation->project, [
                    'version' => $quotation->version,
                    'valid_until' => $quotation->valid_until?->format('Y-m-d'),
                    'days_left' => $quotation->daysLeft(),
                ]);

                return $quotation;
            });

            if ($reminded !== null) {
                $count++;
                QuotationExpiringSoon::dispatch($reminded);
            }
        }

        return $count;
    }

    private function createDownPaymentInvoice(Project $project, Quotation $quotation, User $actor): Invoice
    {
        // Rincian termin pertama: diskon dan PPN mengikuti porsi termin (T3-03).
        $term = $quotation->termBreakdown()[0];

        $invoice = new Invoice([
            'project_id' => $project->id,
            'invoice_number' => InvoiceNumber::next(),
            'type' => InvoiceType::Dp,
            'currency' => $quotation->currency,
            'amount' => $term['amount']->amount,
            'due_date' => now()->addDays(self::DP_DUE_DAYS)->toDateString(),
        ]);
        // Invoice DP otomatis langsung terbit (bukan draf): syarat transisi deal → awaiting_dp.
        $invoice->forceFill([
            'subtotal' => $term['subtotal']->amount,
            'discount_amount' => $term['discount']->amount,
            'tax_amount' => $term['tax']->amount,
            'term_percent_bp' => $quotation->termBps()[0],
            'status' => InvoiceStatus::Unpaid,
            'issued_at' => now(),
        ])->save();

        ActivityLogger::log(ActivityAction::InvoiceCreated, $project, [
            'invoice_number' => $invoice->invoice_number,
            'type' => $invoice->type->value,
            'currency' => $invoice->currency->value,
            'subtotal' => $invoice->subtotal,
            'discount_amount' => $invoice->discount_amount,
            'tax_amount' => $invoice->tax_amount,
            'amount' => $invoice->amount,
        ], $actor);

        return $invoice;
    }

    private function move(Project $project, ProjectStatus $to, User $actor): void
    {
        $from = $project->status;
        $project->transitionTo($to, $actor);

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
