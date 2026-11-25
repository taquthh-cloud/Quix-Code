<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Setting;
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

        $settings = Setting::query()
            ->whereIn('key', [
                'company.name', 'company.address', 'company.npwp',
                'bank.name', 'bank.account_name', 'bank.account_number',
                'invoice.footer.id', 'invoice.footer.en',
            ])
            ->get()
            ->mapWithKeys(fn (Setting $setting) => [
                $setting->key => is_array($setting->value)
                    ? ($setting->value['value'] ?? '')
                    : (string) $setting->value,
            ]);

        return view('invoices.print', [
            'invoice' => $record,
            'backUrl' => $backUrl,
            'company' => [
                'name' => $settings->get('company.name') ?: config('app.name'),
                'address' => $settings->get('company.address'),
                'npwp' => $settings->get('company.npwp'),
            ],
            'bank' => [
                'name' => $settings->get('bank.name'),
                'account_name' => $settings->get('bank.account_name'),
                'account_number' => $settings->get('bank.account_number'),
            ],
            'footers' => [
                'id' => $settings->get('invoice.footer.id', __('projects.invoice.document_footer', [], 'id')),
                'en' => $settings->get('invoice.footer.en', __('projects.invoice.document_footer', [], 'en')),
            ],
        ]);
    }
}
