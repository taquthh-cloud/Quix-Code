<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prioritas bahasa: preferensi akun (users.locale) > cookie "locale" > default aplikasi (id).
 * Prefiks URL publik /id dan /en ditambahkan di Tahap 6.
 */
class SetLocale
{
    public const SUPPORTED = ['id', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        // Middleware can run with an authenticated model loaded using a partial column selection.
        $locale = $request->user()?->getRawOriginal('locale');

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = $request->cookie('locale');
        }

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = config('app.locale');
        }

        App::setLocale($locale);
        Date::setLocale($locale);

        return $next($request);
    }
}
