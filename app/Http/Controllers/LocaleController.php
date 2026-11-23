<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, SetLocale::SUPPORTED, true), 404);

        if ($user = $request->user()) {
            $user->forceFill(['locale' => $locale])->saveQuietly();
        }

        return back()->withCookie(Cookie::forever('locale', $locale));
    }
}
