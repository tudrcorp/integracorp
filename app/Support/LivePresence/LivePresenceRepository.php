<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

/**
 * Dónde vive la presencia en vivo. Dos implementaciones con el mismo contrato:
 * Redis (producción) y la caché de Laravel (desarrollo, base de datos).
 */
interface LivePresenceRepository
{
    /**
     * Mezcla campos en el registro de una sesión y la marca como activa ahora.
     *
     * @param  array<string, scalar|null>  $fields
     */
    public function touch(string $sessionKey, array $fields, bool $countRequest): void;

    /**
     * Sesiones con actividad en los últimos $windowSeconds, la más reciente primero.
     *
     * @return list<array<string, string>>
     */
    public function online(int $windowSeconds): array;

    /**
     * @param  array<string, scalar|null>  $event
     */
    public function pushTimeline(int $userId, array $event): void;

    /**
     * @return list<array<string, mixed>>
     */
    public function timeline(int $userId, int $limit): array;

    public function recordRequestDuration(float $milliseconds): void;

    /**
     * Muestras recientes de tiempo de respuesta: [marca de tiempo, milisegundos].
     *
     * @return list<array{0: int, 1: float}>
     */
    public function durationSamples(): array;

    public function driverName(): string;
}
