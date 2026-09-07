<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Storefront\StorefrontAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfStorefrontAuthenticated
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (StorefrontAuth::check()) {
            if (StorefrontAuth::mustCompleteProfile()) {
                return redirect()->route('storefront.profile');
            }

            return redirect()->route('storefront.home');
        }

        return $next($request);
    }
}
