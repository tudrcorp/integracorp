<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LivePresencePingRequest;
use App\Support\LivePresence\LivePresenceRecorder;
use Illuminate\Http\Response;

/**
 * Recibe el latido del navegador. Responde vacío y no toca MySQL.
 */
class LivePresencePingController extends Controller
{
    public function __invoke(LivePresencePingRequest $request): Response
    {
        if (config('live-presence.enabled', true) && $request->user() !== null) {
            LivePresenceRecorder::recordPing($request, $request->user(), $request->clientData());
        }

        return response()->noContent();
    }
}
