<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\View\View;

class InvoicePrintController extends Controller
{
    public function __invoke(string $invoice): View
    {
        $record = Invoice::query()
            ->visibleTo(auth()->user())
            ->with(['project.client:id,name,email', 'items'])
            ->findOrFail($invoice);

        $this->authorize('download', $record);

        $backUrl = auth()->user()->isAdmin()
            ? route('admin.projects.negotiation', $record->project_id)
            : route('portal.projects.negotiation', $record->project_id);

        return view('invoices.print', ['invoice' => $record, 'backUrl' => $backUrl]);
    }
}
