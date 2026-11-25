<?php

use App\Models\User;

it('halaman pilihan appearance tetap tersedia', function () {
    $this->actingAs(User::factory()->admin()->withTwoFactor()->create())
        ->get(route('appearance.edit'))
        ->assertOk();
});

it('admin bawaannya gelap tetapi pilihan pengguna tidak ditimpa', function () {
    $this->actingAs(User::factory()->admin()->withTwoFactor()->create())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee("if (! localStorage.getItem('flux.appearance'))", false)
        ->assertSee('"dark"', false);
});

it('layout admin memuat varian terang dan gelap', function () {
    $this->actingAs(User::factory()->admin()->withTwoFactor()->create())
        ->get(route('admin.dashboard'))
        ->assertSee('bg-slate-100', false)
        ->assertSee('dark:bg-midnight', false);
});
