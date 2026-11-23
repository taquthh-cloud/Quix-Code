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
        ];

        if ($user->isAdmin()) {
            $invoices = Invoice::query()
                ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::AwaitingVerification->value])
                ->selectRaw('currency, status, count(*) as total, sum(amount) as amount')
                ->groupBy('currency', 'status')
                ->get();

            // Kolom currency dan status sudah di-cast ke enum oleh model Invoice.
            $stats['awaiting_verification'] = (int) $invoices
                ->filter(fn ($row) => $row->status === InvoiceStatus::AwaitingVerification)
                ->sum('total');

            // Total belum dibayar per mata uang (tidak boleh dijumlah lintas mata uang).
            $stats['unpaid'] = $invoices
                ->filter(fn ($row) => $row->status === InvoiceStatus::Unpaid)
                ->map(fn ($row) => Money::of((int) $row->amount, $row->currency)->format())
                ->values()
                ->all();
        }

        $recentProjects = Project::visibleTo($user)
            ->with('client:id,name')
            ->latest()
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentProjects'));
    }
}
