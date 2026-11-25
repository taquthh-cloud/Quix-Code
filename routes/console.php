<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Quotation (T3-06). Zona waktu aplikasi UTC: akhir hari valid_until = 07:00 WIB.
 * Kedaluwarsa dipindai sesaat sesudah tengah malam UTC, pengingat pukul 08:00 WIB.
 */
Schedule::command('quotations:expire')->dailyAt('00:10')->withoutOverlapping();
Schedule::command('quotations:remind')->dailyAt('01:00')->withoutOverlapping();

/* Digest email chat (T3-09): pesan belum dibaca dirangkum satu email per proyek dan pengirim. */
Schedule::command('chat:digest')->everyFiveMinutes()->withoutOverlapping();
