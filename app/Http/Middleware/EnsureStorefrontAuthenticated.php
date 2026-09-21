<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Storefront\StorefrontAccount;
use App\Support\Storefront\StorefrontAuth;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStorefrontAuthenticated
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()
                ->guest(route('storefront.login'))
                ->with('storefront_notice', 'Entra a tu cuenta para usar la app.');
        }

        $user = StorefrontAuth::user();

        if (! StorefrontAuth::canAccessPwa($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('storefront.login')
                ->with('storefront_notice', 'Tu cuenta no está activa para usar la app.');
        }

        $onProfile = $request->routeIs('storefront.profile');
        $onLogout = $request->routeIs('storefront.logout');

        if (! $onProfile && ! $onLogout && StorefrontAuth::mustCompleteProfile($user)) {
            $request->session()->put(StorefrontAccount::SESSION_FORCE_PROFILE, true);

            return redirect()
                ->route('storefront.profile')
                ->with('storefront_notice', 'Completa tu cédula y un teléfono o correo para continuar.');
        }

        return $next($request);
    }
}
