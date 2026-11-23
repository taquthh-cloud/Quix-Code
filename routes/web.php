<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProjectFileController;
use App\Http\Middleware\RequireAdminTwoFactor;
use App\Http\Controllers\Portal\OverviewController;
use App\Livewire\Admin\Leads\Index as LeadsIndex;
use App\Livewire\Admin\Projects\Form as ProjectForm;
use App\Livewire\Admin\Projects\Index as ProjectIndex;
use App\Livewire\Admin\Projects\Negotiation as ProjectNegotiation;
use App\Livewire\Admin\Projects\Show as ProjectShow;
use App\Livewire\Admin\Quotations\Builder as QuotationBuilder;
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

        // Project Control Room (Tahap 2b). Otorisasi detail ada di komponen Livewire (Policy).
        Route::livewire('projects', ProjectIndex::class)->name('projects.index');
        Route::livewire('projects/create', ProjectForm::class)
            ->middleware('can:create,'.Project::class)
            ->name('projects.create');
        Route::livewire('projects/{project}', ProjectShow::class)->name('projects.show');
        Route::livewire('projects/{project}/edit', ProjectForm::class)->name('projects.edit');

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
    });

// Halaman contoh komponen (hanya lokal/testing).
if (app()->environment(['local', 'testing'])) {
    Route::view('design-system', 'design-system.demo')
        ->middleware(['auth', 'verified'])
        ->name('design-system');
}

require __DIR__.'/settings.php';
