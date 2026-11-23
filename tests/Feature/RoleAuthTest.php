<?php

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;

dataset('roles', [
    'admin' => ['admin', 'admin.dashboard'],
    'staff' => ['staff', 'admin.dashboard'],
    'client' => ['client', 'portal.overview'],
]);

test('login lalu /dashboard mengalihkan sesuai peran', function (string $state, string $route) {
    $user = User::factory()->{$state}()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->get(route('dashboard'))->assertRedirect(route($route));
    $this->get(route($route))->assertOk();
})->with('roles');

test('klien tidak bisa membuka area admin', function () {
    $this->actingAs(User::factory()->client()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('admin dan staf tidak bisa membuka portal klien', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get(route('portal.overview'))
        ->assertForbidden();
})->with(['admin', 'staff']);

test('tamu diarahkan ke login dari area admin dan portal', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    $this->get(route('portal.overview'))->assertRedirect(route('login'));
});

test('email belum terverifikasi ditolak dari semua dasbor', function (string $state) {
    $user = User::factory()->{$state}()->unverified()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    $this->actingAs($user)->get(route('admin.dashboard'))->assertRedirect(route('verification.notice'));
    $this->actingAs($user)->get(route('portal.overview'))->assertRedirect(route('verification.notice'));
})->with(['admin', 'staff', 'client']);

test('last_login_at dicatat saat login', function () {
    $user = User::factory()->client()->create();
    expect($user->last_login_at)->toBeNull();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

test('registrasi publik selalu membuat klien', function () {
    $this->post(route('register.store'), [
        'name' => 'Calon Klien',
        'email' => 'calon@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'admin', // upaya eskalasi harus diabaikan
    ]);

    expect(User::where('email', 'calon@example.com')->first()->isClient())->toBeTrue();
});
