<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use App\Support\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

/**
 * Registro de errores del sistema para corregirlos rápido.
 *
 * Cada error que Laravel reporta (peticiones, trabajos y consola) se agrupa
 * por huella: clase + mensaje sin IDs + la línea de nuestro código donde se
 * originó. Por grupo se guarda cuántas veces pasó, a cuántos usuarios afectó,
 * dónde (URL, panel o trabajo) y la traza de nuestro código.
 *
 * Nada de cuerpos de petición ni contraseñas: la URL se guarda sin los valores
 * de la query y los literales de SQL se enmascaran. Todo vive en Redis (o la
 * caché en desarrollo) y vence solo.
 *
 * Un error nuevo, o uno que vuelve después de marcarse resuelto (regresión),
 * deja un aviso pendiente que el vigilante (`live-presence:watch`) envía por
 * WhatsApp y correo sin pasar por la cola.
 */
final class ErrorTracker
{
    public const PREFIX = 'lp:err:';

    public const STATUS_LABELS = [
        'regression' => 'Regresión',
        'new' => 'Nuevo',
        'active' => 'Activo',
        'resolved' => 'Resuelto',
    ];

    /** Por encima de esto por minuto y grupo solo se cuenta: una tormenta no puede saturar Redis. */
    private const DETAIL_PER_MINUTE = 20;

    private const MAX_CONTEXTS = 6;

    private static bool $capturing = false;

    public static function capture(Throwable $exception): void
    {
        if (self::$capturing || ! config('live-presence.enabled', true) || ! config('live-presence.errors.enabled', true)) {
            return;
        }

        self::$capturing = true;

        try {
            LivePresenceStore::safely(static function (LivePresenceRepository $store) use ($exception): void {
                self::record($store, $exception);
            });
        } finally {
            self::$capturing = false;
        }
    }

    /**
     * Grupos de errores, lo urgente primero: regresiones, nuevos, activos y resueltos.
     *
     * @return list<array<string, mixed>>
     */
    public static function groups(int $limit = 100): array
    {
        try {
            $store = LivePresenceStore::repository();
            $fingerprints = array_keys($store->topMembers(self::PREFIX.'idx', 300));
            $resolved = self::resolutions($store);
        } catch (Throwable) {
            return [];
        }

        $keys = [];

        foreach ($fingerprints as $fingerprint) {
            $keys[] = self::PREFIX.'n:'.$fingerprint;
        }

        $counters = $store->counters($keys);
        $now = time();
        $groups = [];

        foreach ($fingerprints as $fingerprint) {
            $group = $store->getValue(self::PREFIX.'g:'.$fingerprint);

            if ($group === null) {
                continue;
            }

            $group = self::present($group, $resolved[$fingerprint] ?? null, $now);
            $group['count'] = max((int) ($counters[self::PREFIX.'n:'.$fingerprint] ?? 0), 1);
            $group['users'] = (int) ($group['users'] ?? 0);
            $groups[] = $group;
        }

        $rank = ['regression' => 0, 'new' => 1, 'active' => 2, 'resolved' => 3];
        usort($groups, static fn (array $a, array $b): int => [$rank[$a['status']], -$a['last_at']] <=> [$rank[$b['status']], -$b['last_at']]);

        return array_slice($groups, 0, $limit);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function group(string $fingerprint): ?array
    {
        foreach (self::groups(300) as $group) {
            if ($group['fingerprint'] === $fingerprint) {
                return $group;
            }
        }

        return null;
    }

    public static function resolve(string $fingerprint, string $actor): void
    {
        LivePresenceStore::safely(function (LivePresenceRepository $store) use ($fingerprint, $actor): void {
            $resolved = self::resolutions($store);
            $resolved[$fingerprint] = ['at' => time(), 'by' => $actor];
            $store->putValue(self::PREFIX.'resolved', $resolved, self::retention());

            $group = $store->getValue(self::PREFIX.'g:'.$fingerprint);

            if ($group !== null) {
                unset($group['regression_at']);
                $store->putValue(self::PREFIX.'g:'.$fingerprint, $group, self::retention());
            }
        });

        self::audit('AUDIT_LIVE_ERROR_RESOLVED', $fingerprint);
    }

    public static function reopen(string $fingerprint): void
    {
        LivePresenceStore::safely(function (LivePresenceRepository $store) use ($fingerprint): void {
            $resolved = self::resolutions($store);
            unset($resolved[$fingerprint]);
            $store->putValue(self::PREFIX.'resolved', $resolved, self::retention());
        });

        self::audit('AUDIT_LIVE_ERROR_REOPENED', $fingerprint);
    }

    /**
     * Olvida un grupo: sale de la lista hasta que vuelva a ocurrir (entonces aparece como nuevo).
     */
    public static function forget(string $fingerprint): void
    {
        LivePresenceStore::safely(function (LivePresenceRepository $store) use ($fingerprint): void {
            $store->forget(self::PREFIX.'g:'.$fingerprint);
            $store->forget(self::PREFIX.'n:'.$fingerprint);
            $store->forget(self::PREFIX.'u:'.$fingerprint);
            $resolved = self::resolutions($store);
            unset($resolved[$fingerprint]);
            $store->putValue(self::PREFIX.'resolved', $resolved, self::retention());
        });

        self::audit('AUDIT_LIVE_ERROR_FORGOTTEN', $fingerprint);
    }

    /**
     * Avisos pendientes de errores nuevos y regresiones. Se vacían al leerlos.
     *
     * @return list<array<string, mixed>>
     */
    public static function pullPendingAlerts(): array
    {
        try {
            $store = LivePresenceStore::repository();
            $alerts = $store->readList(self::PREFIX.'alerts', 50);
            $store->forget(self::PREFIX.'alerts');

            return $alerts;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Texto para pegar en el IDE, en un ticket o a un asistente.
     *
     * @param  array<string, mixed>  $group
     */
    public static function copyText(array $group): string
    {
        $lines = [
            'Error: '.$group['class'].': '.$group['message'],
            'Diagnóstico: '.$group['diagnosis']['title'].' — '.$group['diagnosis']['category_label'],
            'Lanzado en: '.($group['location'] ?: '—'),
            'Origen en nuestro código: '.($group['origin'] ?: '—'),
            'Ocurrencias: '.$group['count'].' · usuarios afectados: '.$group['users'].' · primera vez '.$group['first_at_label'].' · última '.$group['last_at_label'],
        ];

        if (($group['contexts'] ?? []) !== []) {
            $lines[] = 'Dónde: '.implode(' | ', $group['contexts']);
        }

        if (($group['app_frames'] ?? []) !== []) {
            $lines[] = 'Traza (solo nuestro código):';

            foreach (array_slice($group['app_frames'], 0, 12) as $frame) {
                $lines[] = '  '.$frame['file'].($frame['line'] !== null ? ':'.$frame['line'] : '').'  '.$frame['call'];
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Mensaje sin secretos: valores de claves sensibles y literales de SQL enmascarados.
     */
    public static function sanitizeMessage(string $message): string
    {
        $message = (string) preg_replace('/((?:password|passwd|pwd|secret|token|api[_-]?key|authorization|bearer)\s*["\']?\s*[:=]\s*)(["\']?)[^\s"\',&)]+/i', '$1$2***', $message);

        if (preg_match('/^(.*?SQL: )(.*)$/s', $message, $match) === 1) {
            $message = $match[1].(string) preg_replace("/'(?:[^'\\\\]|\\\\.)*'/", '?', $match[2]);
        }

        return mb_strimwidth($message, 0, 1500, '…');
    }

    /**
     * URL sin los valores de la query (pueden llevar tokens o datos personales).
     */
    public static function sanitizeUrl(Request $request): string
    {
        $path = '/'.ltrim($request->path(), '/');
        $keys = array_keys($request->query());

        return $keys === [] ? $path : $path.'?'.implode('&', array_map(static fn (int|string $key): string => $key.'=…', $keys));
    }

    private static function record(LivePresenceRepository $store, Throwable $exception): void
    {
        $parsed = ExceptionFingerprint::fromThrowable($exception);
        $message = self::sanitizeMessage($parsed['message']);
        $fingerprint = ExceptionFingerprint::fingerprint('', $parsed['class'], $message, $parsed['origin']);
        $retention = self::retention();
        $minute = SecurityMonitor::minute();

        $store->increment(SecurityMonitor::metricKey('exceptions', $minute), 7200);
        $count = $store->increment(self::PREFIX.'n:'.$fingerprint, $retention);
        $store->scoreMember(self::PREFIX.'idx', $fingerprint, 1, $retention);

        $context = self::context();
        $users = $context['user_id'] !== null
            ? $store->addToSet(self::PREFIX.'u:'.$fingerprint, (string) $context['user_id'], $retention)
            : null;

        if ($store->increment(self::PREFIX.'rate:'.$fingerprint.':'.$minute, 120) > self::DETAIL_PER_MINUTE) {
            return;
        }

        $key = self::PREFIX.'g:'.$fingerprint;
        $current = $store->getValue($key);
        $now = time();
        $isNew = $current === null;
        $resolution = self::resolutions($store)[$fingerprint] ?? null;
        $isRegression = ! $isNew && $resolution !== null && ! isset($current['regression_at']);

        $contexts = array_values(array_unique([$context['label'], ...((array) ($current['contexts'] ?? []))]));

        $store->putValue($key, [
            'fingerprint' => $fingerprint,
            'class' => $parsed['class'],
            'short_class' => $parsed['short_class'],
            'message' => $message,
            'location' => $parsed['location'],
            'origin' => $parsed['origin'],
            'app_frames' => array_slice($parsed['app_frames'], 0, 15),
            'frames' => array_slice($parsed['frames'], 0, 30),
            'first_at' => (int) ($current['first_at'] ?? $now),
            'last_at' => $now,
            'contexts' => array_slice($contexts, 0, self::MAX_CONTEXTS),
            'users' => $users ?? (int) ($current['users'] ?? 0),
            'last_context' => $context,
            'regression_at' => $isRegression ? $now : ($current['regression_at'] ?? null),
        ], $retention);

        if ($isRegression) {
            $resolved = self::resolutions($store);
            unset($resolved[$fingerprint]);
            $store->putValue(self::PREFIX.'resolved', $resolved, $retention);
        }

        if ($isNew || $isRegression) {
            $store->pushList(self::PREFIX.'alerts', [
                'type' => $isRegression ? 'error_regression' : 'error_new',
                'severity' => $isRegression ? SecurityMonitor::SEVERITY_CRITICAL : SecurityMonitor::SEVERITY_WARNING,
                'title' => $isRegression ? 'Regresión: volvió un error resuelto' : 'Error nuevo en el sistema',
                'detail' => $parsed['short_class'].': '.Str::limit($message, 220).' — '.($parsed['origin'] ?: $parsed['location']).' ('.$context['label'].').',
                'subject' => $fingerprint,
                'count' => $count,
                'at' => $now,
            ], 50, $retention);
        }
    }

    /**
     * Dónde ocurrió: petición (URL, panel, usuario) o trabajo de la cola.
     *
     * @return array{label: string, kind: string, url: string|null, method: string|null, route: string|null, panel: string|null, job: string|null, queue: string|null, user_id: int|null, user_name: string|null, ip: string|null}
     */
    private static function context(): array
    {
        $context = ['label' => 'Consola', 'kind' => 'console', 'url' => null, 'method' => null, 'route' => null, 'panel' => null, 'job' => null, 'queue' => null, 'user_id' => null, 'user_name' => null, 'ip' => null];
        $job = QueueActivityRecorder::currentJob();

        if ($job !== null) {
            return [...$context, 'label' => 'Trabajo '.class_basename($job['job']).' · cola '.$job['queue'], 'kind' => 'job', 'job' => $job['job'], 'queue' => $job['queue']];
        }

        if (app()->runningInConsole()) {
            $command = (string) ($_SERVER['argv'][1] ?? '');

            return [...$context, 'label' => $command !== '' ? 'Consola · '.$command : 'Consola'];
        }

        try {
            $request = request();
            $url = self::sanitizeUrl($request);
            $panel = ActivityContext::panelLabel(ActivityContext::panelFor(ActivityContext::pagePath($request)));
            $user = null;

            try {
                $user = $request->hasSession() ? Auth::user() : null;
            } catch (Throwable) {
            }

            return [
                ...$context,
                'label' => $request->method().' '.$url.' · '.$panel,
                'kind' => 'request',
                'url' => $url,
                'method' => $request->method(),
                'route' => $request->route()?->getName() ?? $request->route()?->uri(),
                'panel' => $panel,
                'user_id' => $user !== null ? (int) $user->getAuthIdentifier() : null,
                'user_name' => $user !== null ? (string) ($user->name ?? '') : null,
                'ip' => ClientLocation::ip($request),
            ];
        } catch (Throwable) {
            return $context;
        }
    }

    /**
     * @param  array<string, mixed>  $group
     * @param  array{at: int, by: string}|null  $resolution
     * @return array<string, mixed>
     */
    private static function present(array $group, ?array $resolution, int $now): array
    {
        $firstAt = (int) ($group['first_at'] ?? $now);
        $lastAt = (int) ($group['last_at'] ?? $now);
        $newWindow = max(1, (int) config('live-presence.errors.new_hours', 24)) * 3600;

        $status = match (true) {
            isset($group['regression_at']) && $group['regression_at'] !== null => 'regression',
            $resolution !== null => 'resolved',
            $firstAt >= $now - $newWindow => 'new',
            default => 'active',
        };

        return [
            ...$group,
            'contexts' => array_values((array) ($group['contexts'] ?? [])),
            'app_frames' => array_values((array) ($group['app_frames'] ?? [])),
            'frames' => array_values((array) ($group['frames'] ?? [])),
            'first_at' => $firstAt,
            'last_at' => $lastAt,
            'first_at_label' => date('d/m/Y H:i', $firstAt),
            'last_at_label' => date('d/m/Y H:i', $lastAt),
            'last_ago' => LiveActivitySnapshot::ago($now - $lastAt),
            'status' => $status,
            'status_label' => self::STATUS_LABELS[$status],
            'resolved_by' => $resolution['by'] ?? null,
            'resolved_at' => isset($resolution['at']) ? date('d/m/Y H:i', (int) $resolution['at']) : null,
            'diagnosis' => FailureDiagnosis::diagnose((string) ($group['class'] ?? ''), (string) ($group['message'] ?? '')),
        ];
    }

    /**
     * @return array<string, array{at: int, by: string}>
     */
    private static function resolutions(LivePresenceRepository $store): array
    {
        return array_filter((array) ($store->getValue(self::PREFIX.'resolved') ?? []), static fn (mixed $entry): bool => is_array($entry));
    }

    private static function retention(): int
    {
        return max(1, (int) config('live-presence.errors.retention_days', 7)) * 86400;
    }

    private static function audit(string $action, string $fingerprint): void
    {
        try {
            SecurityAudit::log($action, 'live-presence.errors', ['fingerprint' => $fingerprint]);
        } catch (Throwable) {
        }
    }
}
