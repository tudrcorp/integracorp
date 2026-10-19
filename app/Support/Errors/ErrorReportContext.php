<?php

declare(strict_types=1);

namespace App\Support\Errors;

use App\Models\User;
use App\Support\LivePresence\ErrorTracker;
use App\Support\LivePresence\ExceptionFingerprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Contexto de un error para reportarlo a Sistemas desde la página de error.
 *
 * Lo arma el servidor al pintar la página (sin consultas: solo la petición y la
 * excepción) y viaja cifrado con la APP_KEY: el navegador no puede inventar ni
 * alterar lo que llega al ticket, y queda atado al usuario que vio el error.
 *
 * Nunca lleva cuerpos de petición, contraseñas ni valores de la query.
 */
final class ErrorReportContext
{
    private const VERSION = 1;

    /** Una página de error abierta más tiempo que esto ya no se acepta. */
    public const MAX_AGE_SECONDS = 6 * 3600;

    private const MAX_PARAMS = 6;

    /**
     * @return array<string, mixed>
     */
    public static function build(Request $request, ?Throwable $exception, int $status, User $user): array
    {
        $route = $request->route();
        $panel = ErrorPage::panel($request);
        $routeName = $route?->getName();

        return [
            'v' => self::VERSION,
            'uid' => (int) $user->getKey(),
            'at' => time(),
            'status' => $status,
            'method' => $request->method(),
            'url' => ErrorTracker::sanitizeUrl($request),
            'host' => $request->getHost(),
            'route' => is_string($routeName) ? $routeName : null,
            'action' => self::actionClass($route?->getActionName()),
            'params' => self::params(self::originalParameters($route)),
            'panel' => $panel['id'] ?? self::panelFromRouteName($routeName),
            'referer' => self::sameHostPath($request),
            'ua' => mb_substr((string) $request->userAgent(), 0, 300),
            'ip' => $request->ip(),
            'detail' => ErrorPage::detail($exception, $status),
            'exception' => $status >= 500 ? self::exception($exception) : null,
        ];
    }

    public static function seal(array $context): string
    {
        return Crypt::encryptString((string) json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Abre un contexto sellado. Nulo si fue alterado, es de otro usuario o ya venció.
     *
     * @return array<string, mixed>|null
     */
    public static function open(string $sealed, User $user, ?int $now = null): ?array
    {
        try {
            $context = json_decode(Crypt::decryptString($sealed), true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        $now ??= time();

        if (! is_array($context)
            || ($context['v'] ?? null) !== self::VERSION
            || ($context['uid'] ?? null) !== (int) $user->getKey()
            || ! is_int($context['at'] ?? null)
            || $context['at'] > $now + 60
            || $now - $context['at'] > self::MAX_AGE_SECONDS
            || ! is_int($context['status'] ?? null)
            || $context['status'] < 400
            || $context['status'] > 599) {
            return null;
        }

        return $context;
    }

    /**
     * Huella para no abrir dos tickets del mismo error: mismo usuario, código,
     * pantalla, registro y (en 5xx) la misma falla de código.
     */
    public static function dedupeKey(array $context): string
    {
        return 'error-report:'.sha1(implode('|', [
            $context['uid'] ?? '',
            $context['status'] ?? '',
            $context['route'] ?? $context['url'] ?? '',
            $context['params']['record'] ?? '',
            $context['exception']['fingerprint'] ?? '',
        ]));
    }

    private static function actionClass(?string $action): ?string
    {
        if ($action === null || $action === '' || $action === 'Closure') {
            return null;
        }

        $class = explode('@', $action)[0];

        return preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*$/', $class) === 1 ? $class : null;
    }

    /**
     * Sin ruta ligada (un 404 de URL inexistente) no hay parámetros.
     *
     * @return array<string, mixed>
     */
    private static function originalParameters(mixed $route): array
    {
        try {
            return $route instanceof Route ? $route->originalParameters() : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Solo parámetros escalares y cortos de la ruta (p. ej. record=462).
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, string>
     */
    private static function params(array $parameters): array
    {
        $params = [];

        foreach ($parameters as $key => $value) {
            if (count($params) >= self::MAX_PARAMS) {
                break;
            }

            if (is_scalar($value)) {
                $params[(string) $key] = mb_strimwidth((string) $value, 0, 80, '…');
            }
        }

        return $params;
    }

    private static function panelFromRouteName(?string $routeName): ?string
    {
        if (is_string($routeName) && preg_match('/^filament\.([a-z0-9_-]+)\./i', $routeName, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    private static function sameHostPath(Request $request): ?string
    {
        $referer = (string) $request->headers->get('referer', '');

        if ($referer === '' || parse_url($referer, PHP_URL_HOST) !== $request->getHost()) {
            return null;
        }

        $path = (string) (parse_url($referer, PHP_URL_PATH) ?? '/');

        return mb_strimwidth($path, 0, 300, '…');
    }

    /**
     * La falla de código real (el 500 genérico envuelve la excepción original),
     * con la misma huella que usa el monitor de errores.
     *
     * @return array{class: string, message: string, location: string, origin: string, frames: list<string>, fingerprint: string}|null
     */
    private static function exception(?Throwable $exception): ?array
    {
        if ($exception === null) {
            return null;
        }

        if ($exception instanceof HttpExceptionInterface && $exception->getPrevious() !== null) {
            $exception = $exception->getPrevious();
        }

        try {
            $parsed = ExceptionFingerprint::fromThrowable($exception);
            $message = ErrorTracker::sanitizeMessage($parsed['message']);

            return [
                'class' => $parsed['class'],
                'message' => mb_strimwidth($message, 0, 600, '…'),
                'location' => $parsed['location'],
                'origin' => $parsed['origin'],
                'frames' => array_map(
                    static fn (array $frame): string => $frame['file'].($frame['line'] !== null ? ':'.$frame['line'] : '').'  '.$frame['call'],
                    array_slice($parsed['app_frames'], 0, 8)
                ),
                'fingerprint' => ExceptionFingerprint::fingerprint('', $parsed['class'], $message, $parsed['origin']),
            ];
        } catch (Throwable) {
            return null;
        }
    }
}
