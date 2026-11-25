<?php

use App\Http\Controllers\Admin\CsvExportController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\InvoicePrintController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PaymentProofController;
use App\Http\Controllers\Portal\OverviewController;
use App\Http\Controllers\ProjectFileController;
use App\Http\Controllers\QuotationPrintController;
use App\Http\Middleware\RequireAdminTwoFactor;
use App\Livewire\Admin\Activity\Index as ActivityIndex;
use App\Livewire\Admin\Billing\Index as BillingIndex;
use App\Livewire\Admin\Leads\Index as LeadsIndex;
use App\Livewire\Admin\Projects\Form as ProjectForm;
use App\Livewire\Admin\Projects\Index as ProjectIndex;
use App\Livewire\Admin\Projects\InvoiceManager;
use App\Livewire\Admin\Projects\Kanban;
use App\Livewire\Admin\Projects\Negotiation as ProjectNegotiation;
use App\Livewire\Admin\Projects\Show as ProjectShow;
use App\Livewire\Admin\Quotations\Builder as QuotationBuilder;
use App\Livewire\Admin\Settings\Index as SettingsIndex;
use App\Livewire\Admin\Team\ClientPreview;
use App\Livewire\Admin\Team\Index as TeamIndex;
use App\Livewire\Portal\Invoices as PortalInvoices;
use App\Livewire\Portal\Negotiation as PortalNegotiation;
use App\Livewire\Portal\ProjectOverview as PortalProjectOverview;
use App\Models\Project;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Pilihan bahasa (ID/EN), disimpan di cookie dan users.locale.
Route::post('locale/{locale}', LocaleController::class)->name('locale.update');
Route::get('files/{file}', ProjectFileController::class)
    ->middleware(['auth', 'verified'])
    ->name('files.download');
Route::get('invoices/{invoice}/print', InvoicePrintController::class)
    ->middleware(['auth', 'verified'])
    ->name('admin.invoices.print');
Route::get('quotations/{quotation}/print', QuotationPrintController::class)
    ->middleware(['auth', 'verified'])
    ->name('quotations.print');
Route::get('payments/{payment}/proof', PaymentProofController::class)
    ->middleware(['auth', 'verified'])
    ->name('admin.payments.proof');

/*
 * Pintu masuk tunggal setelah login. Semua alur Fortify (login, 2FA, passkey,
 * verifikasi email, registrasi) berakhir di /dashboard (config fortify.home),
 * lalu dialihkan ke dasbor sesuai peran.
 */
Route::middleware(['auth', 'verified'])->get('dashboard', function () {
    return redirect()->route(auth()->user()->role->dashboardRoute());
})->name('dashboard');

// Area kerja internal: admin & staf.
Route::middleware(['auth', 'verified', 'can:access-internal', RequireAdminTwoFactor::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('search', SearchController::class)->middleware('can:admin')->name('search');
        Route::get('exports/{type}', CsvExportController::class)->middleware('can:admin')->name('exports');

        // Project Control Room (Tahap 2b). Otorisasi detail ada di komponen Livewire (Policy).
        Route::livewire('projects', ProjectIndex::class)->name('projects.index');
        Route::livewire('projects/create', ProjectForm::class)
            ->middleware('can:create,'.Project::class)
            ->name('projects.create');
        Route::livewire('projects/{project}', ProjectShow::class)->name('projects.show');
        Route::livewire('projects/{project}/edit', ProjectForm::class)->name('projects.edit');
        Route::livewire('projects/{project}/invoices', InvoiceManager::class)->middleware('can:admin')->name('projects.invoices');
        Route::livewire('team', TeamIndex::class)->middleware('can:admin')->name('team.index');
        Route::livewire('team/client-preview/{client}', ClientPreview::class)->middleware('can:admin')->name('team.preview');
        Route::livewire('settings', SettingsIndex::class)->middleware('can:admin')->name('settings');
        Route::livewire('kanban', Kanban::class)->name('kanban');
        Route::livewire('billing', BillingIndex::class)->middleware('can:admin')->name('billing.index');
        Route::livewire('activity', ActivityIndex::class)->middleware('can:admin')->name('activity');

        // Quotation & Negosiasi (Tahap 3). Staf ditolak oleh QuotationPolicy/ChatPolicy di komponen.
        Route::livewire('leads', LeadsIndex::class)->middleware('can:admin')->name('leads.index');
        Route::livewire('projects/{project}/negotiation', ProjectNegotiation::class)->name('projects.negotiation');
        Route::livewire('projects/{project}/quotations/create', QuotationBuilder::class)->name('quotations.create');
        Route::livewire('projects/{project}/quotations/{quotation}', QuotationBuilder::class)->name('quotations.edit');
    });

// Portal klien: hanya klien.
Route::middleware(['auth', 'verified', 'can:access-portal'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {
        Route::get('/', OverviewController::class)->name('overview');
        Route::livewire('projects/{project}', PortalProjectOverview::class)->name('projects.show');
        Route::livewire('projects/{project}/negotiation', PortalNegotiation::class)->name('projects.negotiation');
        Route::livewire('projects/{project}/invoices', PortalInvoices::class)->name('projects.invoices');
    });

// Halaman contoh komponen (hanya lokal/testing).
if (app()->environment(['local', 'testing'])) {
    Route::view('design-system', 'design-system.demo')
        ->middleware(['auth', 'verified'])
        ->name('design-system');
}

require __DIR__.'/settings.php';
