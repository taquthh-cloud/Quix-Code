<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Models\Setting;
use App\Services\QuotationService;
use Illuminate\View\View;

/**
 * Quotation siap cetak / simpan sebagai PDF (T3-06), pola yang sama dengan InvoicePrintController.
 * Dokumen ini untuk klien: operasional dan margin tidak pernah ditampilkan, siapa pun yang mencetak.
 */
class QuotationPrintController extends Controller
{
    public function __invoke(string $quotation): View
    {
        $user = auth()->user();

        // visibleTo: klien lain, draf (untuk klien), dan staf mendapat 404.
        $record = Quotation::query()
            ->visibleTo($user)
            ->with(['project.client:id,name,email', 'items'])
            ->findOrFail($quotation);

        $this->authorize('view', $record);

        $service = app(QuotationService::class);

        $settings = Setting::query()
            ->whereIn('key', ['company.name', 'company.address', 'company.npwp'])
            ->get()
            ->mapWithKeys(fn (Setting $setting) => [
                $setting->key => is_array($setting->value)
                    ? ($setting->value['value'] ?? '')
                    : (string) $setting->value,
            ]);

        // Teks S&K yang tercetak harus yang berlaku saat disetujui. Bila teks di Pengaturan sudah berubah
        // sejak persetujuan, teksnya tidak ditampilkan (hanya versinya) agar tidak menyesatkan.
        $termsCurrent = $record->terms_version === null || $record->terms_version === $service->currentTermsVersion();

        return view('quotations.print', [
            'quotation' => $record,
            'backUrl' => $user->isAdmin()
                ? route('admin.projects.negotiation', $record->project_id)
                : route('portal.projects.negotiation', $record->project_id),
            'company' => [
                'name' => $settings->get('company.name') ?: config('app.name'),
                'address' => $settings->get('company.address'),
                'npwp' => $settings->get('company.npwp'),
            ],
            'terms' => $termsCurrent
                ? ['id' => $service->termsText('id'), 'en' => $service->termsText('en')]
                : ['id' => '', 'en' => ''],
            'termsChanged' => ! $termsCurrent,
        ]);
    }
}
