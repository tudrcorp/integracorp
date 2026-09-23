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

    /**
     * Suma 1 al contador; la ventana ($ttl) empieza con el primer incremento.
     */
    public function increment(string $key, int $ttl): int;

    /**
     * @param  list<string>  $keys
     * @return array<string, int>
     */
    public function counters(array $keys): array;

    /**
     * Agrega un miembro a un conjunto y devuelve cuántos distintos tiene.
     */
    public function addToSet(string $key, string $member, int $ttl): int;

    /**
     * @param  array<string, mixed>  $item
     */
    public function pushList(string $key, array $item, int $size, int $ttl): void;

    /**
     * @return list<array<string, mixed>>
     */
    public function readList(string $key, int $limit): array;

    /**
     * Suma puntaje a un miembro de un ranking.
     */
    public function scoreMember(string $key, string $member, float $score, int $ttl): void;

    /**
     * @return array<string, float> miembro => puntaje, de mayor a menor
     */
    public function topMembers(string $key, int $limit): array;

    /**
     * @param  array<string, mixed>  $value
     */
    public function putValue(string $key, array $value, int $ttl): void;

    /**
     * @return array<string, mixed>|null
     */
    public function getValue(string $key): ?array;

    public function forget(string $key): void;
}
