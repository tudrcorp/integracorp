<?php

declare(strict_types=1);

namespace App\Support\Audit;

use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Throwable;

/**
 * Contexto de la operación en curso para la auditoría de cambios: un identificador común a
 * todo lo que cambia en un mismo clic o job, el origen (panel y pantalla, o proceso
 * automático) y, cuando se está revirtiendo, qué cambio se revierte y por qué.
 *
 * Una petición HTTP es una operación; en la cola, cada job empieza una nueva
 * ({@see self::startJob()}).
 */
final class DataChangeAuditContext
{
    private static ?string $batchId = null;

    private static ?string $job = null;

    /** @var array{audit_id: int, reason: string}|null */
    private static ?array $reverting = null;

    public static function batchId(): string
    {
        return self::$batchId ??= (string) Str::uuid();
    }

    public static function startJob(string $name): void
    {
        self::$batchId = (string) Str::uuid();
        self::$job = Str::limit(class_basename($name), 200, '');
        self::$reverting = null;
    }

    public static function reset(): void
    {
        self::$batchId = null;
        self::$job = null;
        self::$reverting = null;
    }

    /**
     * Ejecuta `$callback` marcando los cambios como reversión del cambio `$auditId`.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function reverting(int $auditId, string $reason, callable $callback): mixed
    {
        $previous = self::$reverting;
        self::$reverting = ['audit_id' => $auditId, 'reason' => $reason];

        try {
            return $callback();
        } finally {
            self::$reverting = $previous;
        }
    }

    /**
     * @return array{audit_id: int, reason: string}|null
     */
    public static function revertingInfo(): ?array
    {
        return self::$reverting;
    }

    /**
     * @return array{source: string, panel: ?string, origin: ?string, ip: ?string, user_agent: ?string}
     */
    public static function describe(): array
    {
        if (self::$job !== null) {
            return ['source' => 'cola', 'panel' => null, 'origin' => self::$job, 'ip' => null, 'user_agent' => null];
        }

        if (app()->runningInConsole()) {
            $arguments = array_values(array_filter(array_slice((array) ($_SERVER['argv'] ?? []), 1), fn (mixed $argument): bool => is_string($argument) && ! str_starts_with($argument, '-')));
            $command = $arguments[0] ?? 'consola';

            return ['source' => 'consola', 'panel' => null, 'origin' => Str::limit((string) $command, 200, ''), 'ip' => null, 'user_agent' => null];
        }

        try {
            $request = request();
            $path = '/'.ltrim($request->path(), '/');

            /** Las acciones de Filament llegan por /livewire/update: la pantalla real es la de origen. */
            if (str_starts_with($path, '/livewire')) {
                $referer = (string) $request->headers->get('referer', '');
                $path = $referer !== '' ? (string) (parse_url($referer, PHP_URL_PATH) ?: $path) : $path;
            }

            $panel = null;

            try {
                $panel = Filament::getCurrentPanel()?->getId();
            } catch (Throwable) {
                $panel = null;
            }

            $panel ??= ($segment = explode('/', trim($path, '/'))[0] ?? '') !== '' ? $segment : null;

            return [
                'source' => 'web',
                'panel' => $panel !== null ? Str::limit($panel, 40, '') : null,
                'origin' => Str::limit($path, 250, ''),
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            ];
        } catch (Throwable) {
            return ['source' => 'web', 'panel' => null, 'origin' => null, 'ip' => null, 'user_agent' => null];
        }
    }
}
