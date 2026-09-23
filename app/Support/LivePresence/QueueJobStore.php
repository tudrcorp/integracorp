<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use Closure;

/**
 * Acceso a los trabajos guardados en una cola, sea cual sea el driver.
 *
 * Cada trabajo se describe con:
 * - bucket: `pending` (espera turno), `delayed` (programado) o `reserved` (lo tomó un worker).
 * - ref: cómo quitarlo (id en `database`, el payload exacto en `redis`).
 * - created_at, available_at, reserved_at, reserved_until: marcas de tiempo si el driver las tiene.
 *
 * @phpstan-type QueueJobEntry array{bucket: string, ref: int|string, queue: string, payload: string, created_at: int|null, available_at: int|null, reserved_at: int|null, reserved_until: int|null}
 */
interface QueueJobStore
{
    /**
     * @param  bool  $fullPayload  false = basta el comienzo del payload (clase del trabajo)
     * @return list<array{bucket: string, ref: int|string, queue: string, payload: string, created_at: int|null, available_at: int|null, reserved_at: int|null, reserved_until: int|null}>
     */
    public function entries(string $queue, bool $fullPayload = false): array;

    /**
     * Quita los trabajos que sigan donde estaban (un pendiente que un worker
     * tomó entre la lectura y el borrado no se toca). Antes de quitar cada uno
     * llama a $archive con su payload completo; si el trabajo ya no estaba,
     * llama a $unarchive con lo que devolvió $archive.
     *
     * @param  list<array<string, mixed>>  $entries
     * @return int trabajos quitados
     */
    public function remove(string $queue, array $entries, ?Closure $archive = null, ?Closure $unarchive = null): int;
}
