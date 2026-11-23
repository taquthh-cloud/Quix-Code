<?php
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Livewire\Admin\Projects\Form as ProjectForm;
use App\Livewire\Admin\Projects\Index as ProjectIndex;
use App\Livewire\Admin\Projects\Show as ProjectShow;
use App\Models\Project;

Route::view('/', 'welcome')->name('home');


// Pilihan bahasa (ID/EN), disimpan di cookie dan users.locale.
Route::post('locale/{locale}', LocaleController::class)->name('locale.update');

/*
 * Pintu masuk tunggal setelah login. Semua alur Fortify (login, 2FA, passkey,
 * verifikasi email, registrasi) berakhir di /dashboard (config fortify.home),
 * lalu dialihkan ke dasbor sesuai peran.
 */
Route::middleware(['auth', 'verified'])->get('dashboard', function () {
    return redirect()->route(auth()->user()->role->dashboardRoute());
})->name('dashboard');

// Area kerja internal: admin & staf.
Route::middleware(['auth', 'verified', 'can:access-internal'])
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
    });

// Portal klien: hanya klien.
Route::middleware(['auth', 'verified', 'can:access-portal'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {
        Route::view('/', 'portal.overview')->name('overview');
    });

// Halaman contoh komponen (hanya lokal/testing).
if (app()->environment(['local', 'testing'])) {
    Route::view('design-system', 'design-system.demo')
        ->middleware(['auth', 'verified'])
        ->name('design-system');
}

require __DIR__.'/settings.php';
