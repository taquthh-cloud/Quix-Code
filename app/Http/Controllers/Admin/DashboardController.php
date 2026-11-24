<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\ProjectStatus;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\RevisionTicket;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** Command Center. Data selalu lewat scope visibleTo; staf tidak pernah menerima data tagihan atau harga. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $byStatus = Project::query()
            ->whereIn('id', Project::visibleIds($user))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $stats = [
            'leads' => (int) ($byStatus[ProjectStatus::Lead->value] ?? 0),
            'active' => (int) ($byStatus[ProjectStatus::Active->value] ?? 0),
            'open_tickets' => RevisionTicket::visibleTo($user)
                ->whereIn('status', [TicketStatus::Open->value, TicketStatus::InProgress->value])
                ->count(),
            'unpaid' => null,
            'awaiting_verification' => null,
            'aging' => null,
        ];

        if ($user->isAdmin()) {
            $invoices = Invoice::query()
                ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Unpaid->value, InvoiceStatus::AwaitingVerification->value])
                ->selectRaw('currency, status, count(*) as total, sum(amount) as amount')
                ->groupBy('currency', 'status')
                ->get();

            // Kolom currency dan status sudah di-cast ke enum oleh model Invoice.
            $stats['awaiting_verification'] = (int) $invoices
                ->filter(fn ($row) => $row->status === InvoiceStatus::AwaitingVerification)
                ->sum('total');

            // Total belum dibayar per mata uang (tidak boleh dijumlah lintas mata uang).
            $stats['unpaid'] = $invoices
                ->filter(fn ($row) => in_array($row->status, [InvoiceStatus::Sent, InvoiceStatus::Unpaid], true))
                ->map(fn ($row) => Money::of((int) $row->amount, $row->currency)->format())
                ->values()
                ->all();

            $stats['aging'] = Cache::remember('admin.dashboard.invoice_aging', 60, function (): array {
                $totals = [
                    '0_7' => [],
                    '8_30' => [],
                    'over_30' => [],
                ];

                Invoice::query()
                    ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Unpaid->value])
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<=', now()->toDateString())
                    ->select(['currency', 'amount', 'paid_amount', 'due_date'])
                    ->cursor()
                    ->each(function (Invoice $invoice) use (&$totals): void {
                        $daysOverdue = (int) $invoice->due_date->startOfDay()->diffInDays(now()->startOfDay());
                        $bucket = $daysOverdue <= 7 ? '0_7' : ($daysOverdue <= 30 ? '8_30' : 'over_30');
                        $currency = $invoice->currency->value;
                        $totals[$bucket][$currency] ??= ['count' => 0, 'amount' => 0];
                        $totals[$bucket][$currency]['count']++;
                        $totals[$bucket][$currency]['amount'] += max(0, $invoice->amount - $invoice->paid_amount);
                    });

                foreach ($totals as $bucket => $currencies) {
                    $totals[$bucket] = collect($currencies)->map(fn (array $row, string $currency) => [
                        'currency' => $currency,
                        'count' => $row['count'],
                        'amount' => Money::of($row['amount'], $currency)->format(),
                    ])->values()->all();
                }

                return $totals;
            });
        }

        $recentProjects = Project::visibleTo($user)
            ->with('client:id,name')
            ->latest()
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentProjects'));
    }
}
