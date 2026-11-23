<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isAdmin() && ! $user->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route('security.edit')->with('status', __('auth.admin_two_factor_required'));
        }

        return $next($request);
    }
}
