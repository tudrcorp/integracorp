<?php

declare(strict_types=1);

namespace App\Support\Collections;

use App\Enums\CollectionFollowUpStatus;
use App\Models\Collection;
use App\Models\CollectionFollowUpEvent;
use App\Models\User;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\SecurityAudit;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Estados de gestión de una cuota en «Cobranza Por Mes», por cuota.
 *
 * El navegador solo dice qué casilla se pulsó y qué veía; la regla («enviado» y «remitido» van
 * juntos, «en revisión» va solo) la aplica siempre el servidor con
 * {@see CollectionFollowUpStatus::toggle()}. Un estado retirado (SEGUIMIENTO) que siga guardado
 * se puede reemplazar o quitar, nunca volver a marcar.
 *
 * Cada cambio va en una transacción con la cuota bloqueada y queda en
 * `collection_follow_up_events` y en SecurityAudit. Si otra persona cambió los estados mientras
 * tanto, no pisa su cambio: devuelve lo vigente para que la pantalla lo muestre.
 *
 * Los mensajes de las excepciones están pensados para mostrarse tal cual al analista.
 */
final class CollectionFollowUp
{
    public const PENDING_STATUS = 'POR PAGAR';

    public static function userCan(): bool
    {
        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::MARK_COLLECTION_FOLLOW_UP);
    }

    /**
     * Pulsa una casilla: la marca o, si ya estaba marcada, la quita.
     *
     * @param  string|null  $expected  lo que el analista veía al pulsar (texto guardado)
     * @return array{status: ?string, changed: bool, conflict: bool}
     *
     * @throws InvalidArgumentException
     */
    public static function toggle(int $collectionId, string $option, ?string $expected, User $actor): array
    {
        $state = CollectionFollowUpStatus::tryFrom(trim($option));

        if ($state === null) {
            throw new InvalidArgumentException('Estado de gestión no válido.');
        }

        return self::write($collectionId, $expected, $actor, fn (?string $current): ?string => CollectionFollowUpStatus::encode(
            CollectionFollowUpStatus::toggle(CollectionFollowUpStatus::decode($current), $state)
        ));
    }

    /**
     * Quita todos los estados de la cuota (incluido uno retirado).
     *
     * @return array{status: ?string, changed: bool, conflict: bool}
     *
     * @throws InvalidArgumentException
     */
    public static function clear(int $collectionId, ?string $expected, User $actor): array
    {
        return self::write($collectionId, $expected, $actor, fn (?string $current): ?string => null);
    }

    /**
     * Historial de la cuota, del más reciente al más antiguo.
     *
     * @return SupportCollection<int, CollectionFollowUpEvent>
     */
    public static function history(int $collectionId, int $limit = 50): SupportCollection
    {
        return CollectionFollowUpEvent::query()
            ->where('collection_id', $collectionId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public static function label(?string $status): string
    {
        return CollectionFollowUpStatus::labelFor($status) ?? 'Sin estado';
    }

    /**
     * @param  \Closure(?string): ?string  $next
     * @return array{status: ?string, changed: bool, conflict: bool}
     *
     * @throws InvalidArgumentException
     */
    private static function write(int $collectionId, ?string $expected, User $actor, \Closure $next): array
    {
        if (! self::userCan()) {
            throw new InvalidArgumentException('No tiene permiso para marcar el estado de gestión de cobranza.');
        }

        $expected = self::stored($expected);

        return DB::transaction(function () use ($collectionId, $expected, $actor, $next): array {
            $collection = Collection::query()
                ->whereKey($collectionId)
                ->lockForUpdate()
                ->first(['id', 'affiliation_code', 'collection_invoice_number', 'status', 'follow_up_status']);

            if ($collection === null) {
                throw new InvalidArgumentException('La cuota ya no existe. Actualice la tabla.');
            }

            if (strtoupper(trim((string) $collection->status)) !== self::PENDING_STATUS) {
                throw new InvalidArgumentException('La cuota '.$collection->collection_invoice_number.' ya no está por pagar («'.$collection->status.'»). Actualice la tabla.');
            }

            $current = self::stored($collection->follow_up_status);

            if ($current !== $expected) {
                return ['status' => $current, 'changed' => false, 'conflict' => true];
            }

            $status = $next($current);

            if (! CollectionFollowUpStatus::isValidCombination($status)) {
                throw new InvalidArgumentException('Esa combinación de estados no está permitida.');
            }

            if ($current === $status) {
                return ['status' => $current, 'changed' => false, 'conflict' => false];
            }

            Collection::query()->whereKey($collection->getKey())->update(['follow_up_status' => $status]);

            CollectionFollowUpEvent::query()->create([
                'collection_id' => $collection->getKey(),
                'affiliation_code' => $collection->affiliation_code,
                'collection_invoice_number' => $collection->collection_invoice_number,
                'previous_status' => $current,
                'new_status' => $status,
                'user_id' => $actor->getKey(),
                'user_name' => $actor->name,
                'ip' => request()?->ip(),
                'user_agent' => Str::limit((string) request()?->userAgent(), 1000, ''),
                'created_at' => now(),
            ]);

            SecurityAudit::log('AUDIT_COLLECTION_FOLLOW_UP_STATUS', 'administration.annual-collections.follow-up', [
                'collection_id' => $collection->getKey(),
                'affiliation_code' => $collection->affiliation_code,
                'from' => $current,
                'to' => $status,
            ], $actor);

            return ['status' => $status, 'changed' => true, 'conflict' => false];
        });
    }

    /**
     * Texto guardado normalizado: vacío → null; el resto tal cual (combinación o retirado).
     */
    private static function stored(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
