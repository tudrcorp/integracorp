<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PresentationHubAuthenticateRequest;
use App\Support\PresentationHubGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PresentationHubController extends Controller
{
    public function index(Request $request): View
    {
        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }

        $intended = (string) $request->query('intended', '');

        if ($intended !== '' && ! PresentationHubGate::isAllowedPath($intended)) {
            $intended = '';
        }

        $authenticated = PresentationHubGate::check();

        if ($authenticated) {
            PresentationHubGate::touch();
        }

        return view('presentation-hub', [
            'sections' => PresentationHubGate::sections(),
            'presentations' => PresentationHubGate::presentations(),
            'authenticated' => $authenticated,
            'intended' => $intended,
            'access' => PresentationHubGate::access(),
            'idleTimeoutSeconds' => PresentationHubGate::idleTimeoutSeconds(),
            'idleExpired' => (bool) session('presentation_hub_idle'),
        ]);
    }

    public function authenticate(PresentationHubAuthenticateRequest $request): JsonResponse|RedirectResponse
    {
        $method = (string) $request->validated('method');
        $credential = (string) $request->validated('credential');
        $intended = (string) ($request->validated('intended') ?? '');

        $colaborador = PresentationHubGate::authenticate($method, $credential);

        if ($colaborador === null) {
            $message = 'No encontramos un colaborador activo con esos datos.';

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => $message,
                ], 422);
            }

            return back()->withErrors(['credential' => $message])->withInput();
        }

        PresentationHubGate::grant($colaborador);

        $redirectTo = PresentationHubGate::isAllowedPath($intended)
            ? '/'.ltrim($intended, '/')
            : '/dpto-tecnologia-sistemas';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'redirect' => $redirectTo,
                'name' => $colaborador->fullName,
                'idle_timeout_seconds' => PresentationHubGate::idleTimeoutSeconds(),
            ]);
        }

        return redirect()->to($redirectTo);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        if (! PresentationHubGate::check()) {
            return response()->json([
                'ok' => false,
                'expired' => true,
            ], 401);
        }

        PresentationHubGate::touch();

        return response()->json([
            'ok' => true,
            'idle_timeout_seconds' => PresentationHubGate::idleTimeoutSeconds(),
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        PresentationHubGate::revoke();

        $redirect = redirect()->to('/dpto-tecnologia-sistemas');

        if ($request->query('reason') === 'idle') {
            $redirect->with('presentation_hub_idle', true);
        }

        return $redirect;
    }
}
