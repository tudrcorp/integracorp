<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use Illuminate\Contracts\Cache\Repository;

/**
 * Contador que sube con cada escritura del CRM. El sondeo de cada bandeja lee solo este número
 * y baja a la base cuando cambia, así el costo no crece con la cantidad de analistas conectados.
 */
final class CrmInboxPulse
{
    private const KEY = 'crm-inbox:pulse';

    public static function bump(): void
    {
        CrmInboxCache::attempt(static function (Repository $store): void {
            $store->add(self::KEY, 0, now()->addDays(7));
            $store->increment(self::KEY);
        });
    }

    /**
     * Pulso de un solo caso: lo sube un cambio que solo importa a quien tiene ese chat abierto
     * (entregado, leído). Así el resto de la bandeja no vuelve a consultar la base.
     */
    public static function bumpThread(string $handoffId): void
    {
        if ($handoffId === '') {
            return;
        }

        CrmInboxCache::attempt(static function (Repository $store) use ($handoffId): void {
            $key = self::KEY.':thread:'.$handoffId;
            $store->add($key, 0, now()->addDays(2));
            $store->increment($key);
        });
    }

    public static function currentThread(?string $handoffId): string
    {
        if ($handoffId === null || $handoffId === '') {
            return '';
        }

        $value = CrmInboxCache::attempt(static fn (Repository $store): mixed => $store->get(self::KEY.':thread:'.$handoffId, 0));

        return is_numeric($value) ? (string) $value : '';
    }

    /**
     * Valor actual, o null si la caché no responde (entonces el sondeo consulta la base).
     */
    public static function current(): ?string
    {
        $value = CrmInboxCache::attempt(static fn (Repository $store): mixed => $store->get(self::KEY, 0));

        return is_numeric($value) ? (string) $value : null;
    }
}
