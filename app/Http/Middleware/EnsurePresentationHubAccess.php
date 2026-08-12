<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\PresentationHubGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePresentationHubAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $hadSession = is_array(session(PresentationHubGate::SESSION_KEY));

        if (! PresentationHubGate::check()) {
            $redirect = redirect()
                ->to('/dpto-tecnologia-sistemas?intended='.urlencode($request->getPathInfo()));

            if ($hadSession) {
                $redirect->with('presentation_hub_idle', true);
            } else {
                $redirect->with('presentation_hub_required', true);
            }

            return $redirect;
        }

        PresentationHubGate::touch();

        return $next($request);
    }
}
