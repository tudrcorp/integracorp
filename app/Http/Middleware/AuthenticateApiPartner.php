<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\ApiPartnerAbility;
use App\Models\ApiPartner;
use App\Models\ApiPartnerAccessLog;
use App\Support\ApiPartners\ApiPartnerToken;
use App\Support\LivePresence\ClientLocation;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Puerta del API de aliados. En orden:
 *
 * 1. Siempre responde JSON y nunca se cachea.
 * 2. Exige HTTPS en producción.
 * 3. Frena por IP los intentos fallidos (fuerza bruta de tokens).
 * 4. Valida el token Bearer contra el hash guardado (comparación en tiempo constante).
 * 5. Aliado activo, vigente y con el permiso del endpoint. El acceso es solo por token:
 *    no se restringe por IP; la IP de cada consulta queda en la bitácora.
 * 6. Límite de peticiones por minuto propio de cada aliado.
 * 7. Deja cada petición en la bitácora, también las rechazadas.
 *
 * Uso en rutas: `api.partner:medications.non_covered.read` (uno o más permisos).
 */
class AuthenticateApiPartner
{
    public const PARTNER_ATTRIBUTE = 'api_partner';

    public const RECORDS_ATTRIBUTE = 'api_partner_records_count';

    /** Motivo interno de un rechazo de token (nunca el secreto); se guarda en la bitácora, no se responde. */
    public const DIAGNOSTIC_ATTRIBUTE = 'api_partner_diagnostic';

    public const MAX_FAILED_ATTEMPTS_PER_MINUTE = 20;

    private const LAST_USED_REFRESH_SECONDS = 60;

    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $startedAt = hrtime(true);
        $request->headers->set('Accept', 'application/json');

        if (! ApiPartner::tablesExist()) {
            return $this->secure(self::error(503, 'servicio_no_disponible', 'El servicio no está disponible en este momento.'));
        }

        $ip = ClientLocation::ip($request);
        $failureKey = 'api-partners:failed:'.sha1($ip);

        if (RateLimiter::tooManyAttempts($failureKey, self::MAX_FAILED_ATTEMPTS_PER_MINUTE)) {
            $retryAfter = RateLimiter::availableIn($failureKey);
            $this->logOncePerMinute('failed:'.$ip, $request, null, 429, ApiPartnerAccessLog::OUTCOME_THROTTLED, $ip, $startedAt);

            return $this->secure(self::error(429, 'demasiados_intentos', 'Demasiados intentos fallidos. Intenta de nuevo más tarde.')
                ->header('Retry-After', (string) $retryAfter));
        }

        if (app()->isProduction() && ! $this->isSecure($request)) {
            return $this->reject($request, null, $ip, $startedAt, 403, ApiPartnerAccessLog::OUTCOME_INSECURE, 'https_requerido', 'La conexión debe hacerse por HTTPS.');
        }

        $token = ApiPartnerToken::normalize($request->bearerToken());

        if ($token === null || $token === '') {
            RateLimiter::hit($failureKey, 60);

            return $this->reject($request, null, $ip, $startedAt, 401, ApiPartnerAccessLog::OUTCOME_MISSING_TOKEN, 'no_autenticado', 'Falta el token de acceso (encabezado Authorization: Bearer).');
        }

        [$partner, $failure, $diagnostic] = $this->resolvePartner($token);

        if ($partner === null) {
            RateLimiter::hit($failureKey, 60);
            $request->attributes->set(self::DIAGNOSTIC_ATTRIBUTE, $diagnostic);

            return $this->reject($request, null, $ip, $startedAt, 401, $failure, 'no_autenticado', 'El token de acceso no es válido.');
        }

        if (! $partner->is_active) {
            return $this->reject($request, $partner, $ip, $startedAt, 403, ApiPartnerAccessLog::OUTCOME_INACTIVE, 'acceso_inactivo', 'El acceso de este aliado está desactivado. Contacta a TDG.');
        }

        if ($partner->isExpired()) {
            return $this->reject($request, $partner, $ip, $startedAt, 403, ApiPartnerAccessLog::OUTCOME_EXPIRED, 'acceso_vencido', 'El acceso de este aliado está vencido. Contacta a TDG.');
        }

        foreach ($abilities as $required) {
            $ability = ApiPartnerAbility::tryFrom($required);

            if ($ability === null || ! $partner->hasAbility($ability)) {
                return $this->reject($request, $partner, $ip, $startedAt, 403, ApiPartnerAccessLog::OUTCOME_FORBIDDEN, 'sin_permiso', 'Este aliado no tiene permiso para consultar este recurso.');
            }
        }

        $limit = max(ApiPartner::MIN_RATE_LIMIT, (int) $partner->rate_limit_per_minute);
        $partnerKey = 'api-partners:partner:'.$partner->getKey();

        if (RateLimiter::tooManyAttempts($partnerKey, $limit)) {
            $retryAfter = RateLimiter::availableIn($partnerKey);
            $this->logOncePerMinute('partner:'.$partner->getKey(), $request, $partner, 429, ApiPartnerAccessLog::OUTCOME_THROTTLED, $ip, $startedAt);

            return $this->secure(self::error(429, 'limite_excedido', 'Se superó el límite de '.$limit.' peticiones por minuto.')
                ->header('Retry-After', (string) $retryAfter)
                ->header('X-RateLimit-Limit', (string) $limit)
                ->header('X-RateLimit-Remaining', '0'));
        }

        RateLimiter::hit($partnerKey, 60);
        $request->attributes->set(self::PARTNER_ATTRIBUTE, $partner);

        $response = $next($request);

        $this->touchLastUsed($partner, $ip);
        $status = $response->getStatusCode();
        $this->log($request, $partner, $status, $status >= 500 ? ApiPartnerAccessLog::OUTCOME_ERROR : ApiPartnerAccessLog::OUTCOME_OK, $ip, $startedAt);

        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, RateLimiter::remaining($partnerKey, $limit)));

        return $this->secure($response);
    }

    public static function partner(Request $request): ?ApiPartner
    {
        $partner = $request->attributes->get(self::PARTNER_ATTRIBUTE);

        return $partner instanceof ApiPartner ? $partner : null;
    }

    /**
     * El controlador informa cuántos registros entregó, para la bitácora.
     */
    public static function recordCount(Request $request, int $count): void
    {
        $request->attributes->set(self::RECORDS_ATTRIBUTE, max(0, $count));
    }

    public static function error(int $status, string $code, string $message): JsonResponse
    {
        $response = response()->json(['error' => $code, 'mensaje' => $message], $status);

        if ($status === 401) {
            $response->header('WWW-Authenticate', 'Bearer');
        }

        return $response;
    }

    /**
     * El aliado dueño del token, o por qué se rechaza. Al cliente siempre se le responde lo
     * mismo (401); el motivo detallado queda solo en la bitácora para poder dar soporte.
     *
     * @return array{0: ApiPartner|null, 1: string, 2: string|null}
     */
    private function resolvePartner(string $token): array
    {
        $prefix = ApiPartnerToken::prefixOf($token);

        if ($prefix === null) {
            return [null, ApiPartnerAccessLog::OUTCOME_MALFORMED_TOKEN, ApiPartnerToken::formatProblem($token)];
        }

        $partner = ApiPartner::query()->where('token_prefix', $prefix)->first();

        if ($partner === null) {
            return [null, ApiPartnerAccessLog::OUTCOME_UNKNOWN_TOKEN, 'prefijo '.$prefix.' no pertenece a ningún aliado (¿token rotado o revocado?)'];
        }

        if (! ApiPartnerToken::matches($token, $partner->token_hash)) {
            return [null, ApiPartnerAccessLog::OUTCOME_TOKEN_MISMATCH, 'prefijo '.$prefix.' de '.$partner->name.', pero el secreto no coincide (¿copiado incompleto?)'];
        }

        return [$partner, ApiPartnerAccessLog::OUTCOME_OK, null];
    }

    /**
     * Detrás de un proxy que termina TLS, el esquema llega en X-Forwarded-Proto.
     */
    private function isSecure(Request $request): bool
    {
        return $request->secure() || strtolower((string) $request->header('X-Forwarded-Proto')) === 'https';
    }

    private function reject(Request $request, ?ApiPartner $partner, string $ip, int|float $startedAt, int $status, string $outcome, string $code, string $message): Response
    {
        $this->log($request, $partner, $status, $outcome, $ip, $startedAt);

        return $this->secure(self::error($status, $code, $message));
    }

    private function secure(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    private function touchLastUsed(ApiPartner $partner, string $ip): void
    {
        if ($partner->last_used_at !== null && $partner->last_used_at->diffInSeconds(now(), true) < self::LAST_USED_REFRESH_SECONDS && $partner->last_used_ip === $ip) {
            return;
        }

        try {
            ApiPartner::query()->whereKey($partner->getKey())->toBase()->update([
                'last_used_at' => now(),
                'last_used_ip' => Str::limit($ip, 45, ''),
            ]);
        } catch (Throwable $exception) {
            Log::warning('API aliados: no se pudo registrar el último uso.', ['partner_id' => $partner->getKey(), 'error' => $exception->getMessage()]);
        }
    }

    /**
     * Un cliente que insiste pasado el límite no debe llenar la bitácora: se anota una vez por minuto.
     */
    private function logOncePerMinute(string $key, Request $request, ?ApiPartner $partner, int $status, string $outcome, string $ip, int|float $startedAt): void
    {
        if (Cache::add('api-partners:throttle-logged:'.sha1($key), true, 60)) {
            $this->log($request, $partner, $status, $outcome, $ip, $startedAt);
        }
    }

    private function log(Request $request, ?ApiPartner $partner, int $status, string $outcome, string $ip, int|float $startedAt): void
    {
        try {
            $records = $request->attributes->get(self::RECORDS_ATTRIBUTE);

            ApiPartnerAccessLog::query()->create([
                'api_partner_id' => $partner?->getKey(),
                'method' => Str::limit($request->method(), 10, ''),
                'path' => Str::limit('/'.ltrim($request->path(), '/'), 255, ''),
                'ip' => Str::limit($ip, 45, ''),
                'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                'status_code' => $status,
                'outcome' => $outcome,
                'query' => self::loggableQuery($request->query(), $request->attributes->get(self::DIAGNOSTIC_ATTRIBUTE)),
                'records_count' => is_int($records) ? $records : null,
                'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            ]);
        } catch (Throwable $exception) {
            Log::error('API aliados: no se pudo escribir la bitácora de acceso.', [
                'partner_id' => $partner?->getKey(),
                'status' => $status,
                'outcome' => $outcome,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Filtros recibidos, acotados en cantidad y largo para que la bitácora no crezca sin control.
     *
     * @param  array<int|string, mixed>  $query
     * @return array<string, string>|null
     */
    public static function loggableQuery(array $query, mixed $diagnostic = null): ?array
    {
        $loggable = [];

        if (is_string($diagnostic) && $diagnostic !== '') {
            $loggable['_diagnostico'] = Str::limit($diagnostic, 160);
        }

        foreach (array_slice($query, 0, 15, true) as $key => $value) {
            $value = is_array($value) ? implode(',', array_map(fn (mixed $item): string => is_scalar($item) ? (string) $item : '…', array_slice($value, 0, 10))) : (is_scalar($value) ? (string) $value : '…');
            $loggable[Str::limit((string) $key, 40, '')] = Str::limit($value, 120);
        }

        return $loggable === [] ? null : $loggable;
    }
}
