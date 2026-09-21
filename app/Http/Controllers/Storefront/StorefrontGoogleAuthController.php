<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Support\Storefront\StorefrontAccount;
use App\Support\Storefront\StorefrontAuth;
use App\Support\Storefront\StorefrontGoogleAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class StorefrontGoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if (StorefrontAuth::check()) {
            return redirect()->route('storefront.home');
        }

        if (! StorefrontGoogleAuth::isConfigured()) {
            return redirect()
                ->route('storefront.register')
                ->with('storefront_notice', 'Google aún no está configurado. Crea tu cuenta con el formulario.');
        }

        $throttleKey = 'storefront-google:'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 8)) {
            return redirect()
                ->route('storefront.login')
                ->with('storefront_notice', 'Demasiados intentos con Google. Espera un minuto e inténtalo de nuevo.');
        }

        RateLimiter::hit($throttleKey, 60);

        $state = Str::random(40);
        $verifier = StorefrontGoogleAuth::generateCodeVerifier();
        $request->session()->put(StorefrontGoogleAuth::SESSION_STATE, $state);
        $request->session()->put(StorefrontGoogleAuth::SESSION_VERIFIER, $verifier);

        return redirect()->away(StorefrontGoogleAuth::authorizationUrl($state, $verifier));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = (string) $request->session()->pull(StorefrontGoogleAuth::SESSION_STATE, '');
        $codeVerifier = (string) $request->session()->pull(StorefrontGoogleAuth::SESSION_VERIFIER, '');
        $returnedState = (string) $request->query('state', '');

        if ($expectedState === '' || $codeVerifier === '' || ! hash_equals($expectedState, $returnedState)) {
            return redirect()
                ->route('storefront.login')
                ->with('storefront_notice', 'No pudimos confirmar el acceso con Google. Inténtalo otra vez.');
        }

        if ($request->filled('error')) {
            return redirect()
                ->route('storefront.login')
                ->with('storefront_notice', 'Cancelaste el acceso con Google.');
        }

        $code = trim((string) $request->query('code', ''));

        if ($code === '') {
            return redirect()
                ->route('storefront.login')
                ->with('storefront_notice', 'Google no devolvió un código de acceso.');
        }

        try {
            $profile = StorefrontGoogleAuth::userFromAuthorizationCode($code, $codeVerifier);
            $user = StorefrontAccount::createFromGoogle($profile['email'], $profile['name']);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?: 'No pudimos crear tu cuenta con Google.';

            return redirect()
                ->route('storefront.login')
                ->with('storefront_notice', $message);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('storefront.login')
                ->with('storefront_notice', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('storefront.login')
                ->with('storefront_notice', 'No pudimos entrar con Google. Inténtalo de nuevo.');
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->put(StorefrontAccount::SESSION_FORCE_PROFILE, true);

        return redirect()->route('storefront.profile');
    }
}
