<?php

namespace App\Support;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Enums\QuotationStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Quotation;
use Illuminate\Support\Collection;

/**
 * T4-02. Menentukan peringatan aksi berikutnya untuk klien dari status proyek, quotation,
 * dan invoice. Hanya membaca relasi yang sudah dimuat (quotations, invoices.payments), jadi
 * aman dipakai untuk banyak proyek sekaligus tanpa N+1. Draf tidak pernah dipertimbangkan.
 */
final class ProjectNextActions
{
    private const MAX_ACTIONS = 4;

    /** @return list<NextAction> terurut dari yang paling mendesak */
    public static function for(Project $project): array
    {
        $project->loadMissing(['quotations', 'invoices.payments']);

        $status = self::projectStatus($project);

        if ($status->isTerminal()) {
            return [];
        }

        $actions = [
            ...self::invoiceActions($project),
            ...self::quotationActions($project, $status),
            ...self::statusActions($project, $status),
        ];

        usort($actions, fn (NextAction $a, NextAction $b): int => $a->priority <=> $b->priority);

        return array_slice($actions, 0, self::MAX_ACTIONS);
    }

    public static function primary(Project $project): ?NextAction
    {
        return self::for($project)[0] ?? null;
    }

    /** @return list<NextAction> */
    private static function invoiceActions(Project $project): array
    {
        $actions = [];
        $verifying = null;

        foreach (self::invoices($project) as $invoice) {
            $status = self::invoiceStatus($invoice);

            if ($status === InvoiceStatus::AwaitingVerification) {
                $verifying ??= $invoice;

                continue;
            }

            if (! $status->acceptsPayment()) {
                continue;
            }

            $remaining = $invoice->money()->subtract(Money::of((int) $invoice->getAttribute('paid_amount'), $invoice->currency));
            $key = match (true) {
                self::lastPaymentRejected($invoice) => 'resubmit_payment',
                $invoice->isOverdue() => 'pay_overdue',
                default => match (self::invoiceType($invoice)) {
                    InvoiceType::Dp => 'pay_dp',
                    InvoiceType::Final => 'pay_final',
                    InvoiceType::Termin => 'pay_invoice',
                },
            };

            $actions[] = self::make($key, [
                'number' => (string) $invoice->invoice_number,
                'amount' => $remaining->format(),
            ], match ($key) {
                'resubmit_payment' => 10,
                'pay_overdue' => 20,
                'pay_dp' => 30,
                'pay_final' => 40,
                default => 50,
            }, NextAction::TONE_ACTION, self::invoicesUrl($project));
        }

        if ($verifying !== null) {
            $actions[] = self::make('payment_verifying', ['number' => (string) $verifying->invoice_number], 70, NextAction::TONE_WAITING, self::invoicesUrl($project));
        }

        return $actions;
    }

    /** @return list<NextAction> */
    private static function quotationActions(Project $project, ProjectStatus $status): array
    {
        if (! in_array($status, [ProjectStatus::Lead, ProjectStatus::Quoted, ProjectStatus::Negotiating], true)) {
            return [];
        }

        $chat = self::chatUrl($project);
        $quotations = self::quotations($project);
        $sent = $quotations->first(fn (Quotation $q): bool => self::quotationStatus($q) === QuotationStatus::Sent);

        if ($sent !== null && ! $sent->isExpired()) {
            $left = $sent->daysLeft();
            $key = match (true) {
                $sent->isExpiringSoon() && $left === 0 => 'review_quotation_today',
                $sent->isExpiringSoon() => 'review_quotation_soon',
                default => 'review_quotation',
            };

            return [self::make($key, ['days' => (string) $left], 60, NextAction::TONE_ACTION, $chat)];
        }

        $expired = $sent !== null
            || $quotations->contains(fn (Quotation $q): bool => self::quotationStatus($q) === QuotationStatus::Expired);

        if ($expired) {
            return [self::make('quotation_expired', [], 65, NextAction::TONE_ACTION, $chat)];
        }

        if ($status === ProjectStatus::Lead && $quotations->isEmpty()) {
            return [self::make('awaiting_quotation', [], 80, NextAction::TONE_WAITING, $chat)];
        }

        return [self::make('awaiting_revision', [], 75, NextAction::TONE_WAITING, $chat)];
    }

    /** @return list<NextAction> */
    private static function statusActions(Project $project, ProjectStatus $status): array
    {
        return match ($status) {
            ProjectStatus::Handover => [self::make('handover_ready', [], 85, NextAction::TONE_INFO, route('portal.projects.show', $project->getKey()))],
            ProjectStatus::OnHold => [self::make('on_hold', [], 90, NextAction::TONE_INFO, null)],
            default => [],
        };
    }

    /** @param array<string, string> $params */
    private static function make(string $key, array $params, int $priority, string $tone, ?string $url): NextAction
    {
        $label = __("next_action.{$key}.label", $params);

        return new NextAction(
            key: $key,
            tone: $tone,
            priority: $priority,
            title: __("next_action.{$key}.title", $params),
            description: __("next_action.{$key}.description", $params),
            label: $url !== null && is_string($label) && $label !== "next_action.{$key}.label" ? $label : null,
            url: $url,
        );
    }

    private static function invoicesUrl(Project $project): string
    {
        return route('portal.projects.invoices', $project->getKey());
    }

    private static function chatUrl(Project $project): string
    {
        return route('portal.projects.negotiation', $project->getKey());
    }

    /**
     * Invoice yang masih perlu perhatian klien: bukan draf, void, batal, atau lunas; terlama lebih dulu.
     *
     * @return Collection<int, Invoice>
     */
    private static function invoices(Project $project): Collection
    {
        /** @var Collection<int, Invoice> $all */
        $all = $project->getRelation('invoices');

        return $all
            ->reject(fn (Invoice $i): bool => in_array(self::invoiceStatus($i), [InvoiceStatus::Draft, InvoiceStatus::Void, InvoiceStatus::Cancelled, InvoiceStatus::Paid], true))
            ->sortBy(fn (Invoice $i) => $i->created_at)
            ->values();
    }

    /**
     * Quotation yang terlihat klien (tanpa draf), terbaru lebih dulu.
     *
     * @return Collection<int, Quotation>
     */
    private static function quotations(Project $project): Collection
    {
        /** @var Collection<int, Quotation> $all */
        $all = $project->getRelation('quotations');

        return $all
            ->reject(fn (Quotation $q): bool => self::quotationStatus($q) === QuotationStatus::Draft)
            ->sortByDesc(fn (Quotation $q) => $q->created_at)
            ->values();
    }

    private static function lastPaymentRejected(Invoice $invoice): bool
    {
        /** @var Collection<int, Payment> $payments */
        $payments = $invoice->getRelation('payments');
        $last = $payments->sortByDesc(fn (Payment $p) => $p->getKey())->first();

        if ($last === null) {
            return false;
        }

        $status = $last->getAttribute('status');

        return $status === PaymentStatus::Rejected || $status === PaymentStatus::Rejected->value;
    }

    private static function projectStatus(Project $project): ProjectStatus
    {
        $value = $project->getAttribute('status');

        return $value instanceof ProjectStatus ? $value : ProjectStatus::from((string) $value);
    }

    private static function invoiceStatus(Invoice $invoice): InvoiceStatus
    {
        $value = $invoice->getAttribute('status');

        return $value instanceof InvoiceStatus ? $value : InvoiceStatus::from((string) $value);
    }

    private static function invoiceType(Invoice $invoice): InvoiceType
    {
        $value = $invoice->getAttribute('type');

        return $value instanceof InvoiceType ? $value : InvoiceType::from((string) $value);
    }

    private static function quotationStatus(Quotation $quotation): QuotationStatus
    {
        $value = $quotation->getAttribute('status');

        return $value instanceof QuotationStatus ? $value : QuotationStatus::from((string) $value);
    }
}
