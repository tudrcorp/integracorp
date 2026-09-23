<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Validated;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Engancha la seguridad del monitor a los eventos de autenticación de Laravel,
 * sin tocar los formularios de login de los paneles:
 *
 * - Attempting: una cuenta bajo bloqueo temporal no puede ni intentarlo.
 * - Failed: cada login fallido alimenta la detección de ataques.
 * - Validated: un usuario en la lista negra no inicia sesión aunque la clave sea correcta.
 * - Authenticated: un usuario bloqueado con sesión abierta queda fuera en su próxima petición.
 */
final class SecurityAuthListener
{
    /** El campo de correo del login de Filament es `data.email`; `email` cubre otros formularios. */
    private const LOGIN_FIELDS = ['data.email', 'email'];

    private static bool $handlingBlocked = false;

    public static function onAttempting(Attempting $event): void
    {
        $identifier = self::identifier($event->credentials);

        if ($identifier === '') {
            return;
        }

        $lock = SecurityMonitor::accountLock($identifier);

        if ($lock !== null) {
            throw ValidationException::withMessages(array_fill_keys(self::LOGIN_FIELDS, SecurityMonitor::lockMessage($lock)));
        }
    }

    public static function onFailed(Failed $event): void
    {
        try {
            SecurityMonitor::recordFailedLogin(request(), self::identifier($event->credentials), self::channel());
        } catch (Throwable) {
        }
    }

    public static function onValidated(Validated $event): void
    {
        if (UserBlockList::isBlocked((int) $event->user->getAuthIdentifier())) {
            throw ValidationException::withMessages(array_fill_keys(self::LOGIN_FIELDS, self::blockedMessage()));
        }
    }

    public static function onLogin(Login $event): void
    {
        try {
            SecurityMonitor::recordSuccessfulLogin((string) ($event->user->email ?? ''));
        } catch (Throwable) {
        }
    }

    public static function onAuthenticated(Authenticated $event): void
    {
        if (self::$handlingBlocked || ! UserBlockList::isBlocked((int) $event->user->getAuthIdentifier())) {
            return;
        }

        self::$handlingBlocked = true;

        try {
            Auth::guard($event->guard)->logout();

            if (request()->hasSession()) {
                request()->session()->invalidate();
                request()->session()->regenerateToken();
            }
        } catch (Throwable) {
        } finally {
            self::$handlingBlocked = false;
        }

        throw new HttpResponseException(response()->view('live-presence.blocked', [
            'message' => self::blockedMessage(),
        ], 403));
    }

    public static function blockedMessage(): string
    {
        return 'Su acceso a IntegraCorp fue bloqueado por el administrador. Si cree que es un error, contacte a soporte.';
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private static function identifier(array $credentials): string
    {
        foreach (['email', 'identifier', 'username', 'phone'] as $field) {
            if (is_string($credentials[$field] ?? null) && trim($credentials[$field]) !== '') {
                return trim($credentials[$field]);
            }
        }

        return '';
    }

    private static function channel(): string
    {
        $path = ActivityContext::pagePath(request());

        return ActivityContext::panelLabel(ActivityContext::panelFor($path));
    }
}
