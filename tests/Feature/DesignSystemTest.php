<?php

use App\Enums\ProjectStatus;
use App\Models\User;

beforeEach(fn () => app()->setLocale('id'));

test('progress bar membatasi nilai 0-100', function () {
    $this->blade('<x-progress-bar :value="150" />')->assertSee('aria-valuenow="100"', false);
    $this->blade('<x-progress-bar :value="-5" />')->assertSee('aria-valuenow="0"', false);
});

test('badge status memakai label enum', function () {
    $this->blade('<x-badge-status :status="$s" />', ['s' => ProjectStatus::AwaitingDp])
        ->assertSee('Menunggu DP');
});

test('tabel kosong menampilkan empty state', function () {
    $this->blade('<x-table :empty="true" />')->assertSee('Belum ada data');
});

test('halaman contoh memakai semua komponen dasar', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('design-system'))
        ->assertOk()
        ->assertSee('Design System')
        ->assertSee('role="progressbar"', false)
        ->assertSee('role="dialog"', false)
        ->assertSee('Belum ada data');
});

test('bahasa default adalah Indonesia', function () {
    expect(config('app.locale'))->toBe('id');
});

test('akun dengan locale en melihat teks Inggris', function () {
    $this->actingAs(User::factory()->admin()->create(['locale' => 'en']))
        ->get(route('design-system'))
        ->assertSee('Status badges');
});

test('pengguna bisa mengganti bahasa dan tersimpan di akun', function () {
    $user = User::factory()->client()->create();

    $this->actingAs($user)->from('/')->post(route('locale.update', 'en'))
        ->assertRedirect('/')
        ->assertCookie('locale', 'en');

    expect($user->fresh()->locale)->toBe('en');
});

test('bahasa tidak didukung ditolak', function () {
    $this->post(route('locale.update', 'fr'))->assertNotFound();
});
