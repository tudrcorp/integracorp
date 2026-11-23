<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates\Population;

use App\Models\CorporatePopulationUpdate;
use App\Models\CorporatePopulationUpdateItem as Item;
use App\Models\User;
use App\Support\Filament\UserNavigationAccess;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Libera una carga trabada en «Aplicando» o «Revirtiendo».
 *
 * Aplicar y revertir corren en cola, en una sola transacción (todo o nada). Si el
 * trabajo se pierde sin ejecutarse (un worker caído o ajeno a la aplicación), la
 * carga queda en ese estatus para siempre y bloquea cualquier otra aprobación de la
 * afiliación. Esta acción hace lo mismo que el propio trabajo al fallar: devuelve la
 * carga a «Analizada» (o a «Aplicada» si estaba revirtiendo), sin tocar afiliados.
 *
 * Solo superadmin, con razón obligatoria, después de {@see self::STALE_MINUTES} minutos
 * y solo si ningún ítem cambió: si alguno quedó aplicado (o revertido), no se toca.
 */
final class PopulationUpdateStuckRelease
{
    public const STALE_MINUTES = 30;

    public const REASON_MIN_LENGTH = 15;

    public static function canRelease(?User $user): bool
    {
        return $user instanceof User && UserNavigationAccess::isSuperAdmin($user);
    }

    public static function isStuckStatus(CorporatePopulationUpdate $update): bool
    {
        return in_array($update->status, [CorporatePopulationUpdate::STATUS_APPLYING, CorporatePopulationUpdate::STATUS_ROLLING_BACK], true);
    }

    /**
     * Desde cuándo está en el estatus: aprobar y revertir actualizan `updated_at` al marcarla.
     */
    public static function busySinceMinutes(CorporatePopulationUpdate $update): int
    {
        return (int) floor(($update->updated_at ?? now())->diffInMinutes(now(), true));
    }

    /**
     * Por qué no se puede liberar todavía; null si se puede.
     */
    public static function blockingReason(CorporatePopulationUpdate $update): ?string
    {
        if (! self::isStuckStatus($update)) {
            return 'Solo se libera una carga en «Aplicando» o «Revirtiendo».';
        }

        $minutes = self::busySinceMinutes($update);

        if ($minutes < self::STALE_MINUTES) {
            return 'La carga lleva '.$minutes.' min en «'.$update->statusLabel().'». Espere al menos '.self::STALE_MINUTES.' min: puede seguir procesándose.';
        }

        $changed = self::changedItems($update);

        if ($changed > 0) {
            return $changed.' '.($changed === 1 ? 'fila ya cambió' : 'filas ya cambiaron').' en los afiliados, así que la carga no está trabada sin efecto. No se libera: revise el detalle o pida apoyo técnico.';
        }

        return null;
    }

    public static function release(CorporatePopulationUpdate $update, User $user, string $reason): CorporatePopulationUpdate
    {
        if (! self::canRelease($user)) {
            throw new RuntimeException('Solo un superadmin puede liberar una carga trabada.');
        }

        $reason = Str::squish($reason);

        if (mb_strlen($reason) < self::REASON_MIN_LENGTH) {
            throw new RuntimeException('Indique la razón (mínimo '.self::REASON_MIN_LENGTH.' caracteres).');
        }

        /** Si el trabajo de aplicar o revertir está corriendo ahora mismo, tiene este candado. */
        $lock = Cache::lock(PopulationUpdateApplier::lockKey((int) $update->affiliation_corporate_id), 60);

        if (! $lock->get()) {
            throw new RuntimeException('La carga se está procesando en este momento. Espere a que termine.');
        }

        try {
            return DB::transaction(static function () use ($update, $user, $reason): CorporatePopulationUpdate {
                $locked = CorporatePopulationUpdate::query()->whereKey($update->getKey())->lockForUpdate()->firstOrFail();

                $blocker = self::blockingReason($locked);

                if ($blocker !== null) {
                    throw new RuntimeException($blocker);
                }

                $previous = $locked->status;
                $wasApplying = $previous === CorporatePopulationUpdate::STATUS_APPLYING;
                $note = 'Liberada por '.$user->name.' el '.now()->format('d/m/Y h:i A').': el proceso no terminó y no se cambió ningún afiliado. Razón: '.$reason;

                $locked->forceFill($wasApplying
                    ? [
                        'status' => CorporatePopulationUpdate::STATUS_ANALYZED,
                        'approved_by' => null,
                        'approved_by_name' => null,
                        'approved_at' => null,
                        'error' => Str::limit($note, 2000, ''),
                    ]
                    : [
                        'status' => CorporatePopulationUpdate::STATUS_APPLIED,
                        'rolled_back_by' => null,
                        'rolled_back_by_name' => null,
                        'rolled_back_at' => null,
                        'rollback_note' => null,
                        'error' => Str::limit($note, 2000, ''),
                    ])->save();

                SecurityAudit::log('AUDIT_CORPORATE_POPULATION_RELEASED', 'business.corporate-population.release', [
                    'update_id' => $locked->getKey(),
                    'affiliation_corporate_id' => $locked->affiliation_corporate_id,
                    'from_status' => $previous,
                    'to_status' => $locked->status,
                    'reason' => $reason,
                ], $user);

                return $locked;
            });
        } finally {
            $lock->release();
        }
    }

    /**
     * Ítems que ya no están como antes del proceso: aplicados (si estaba aplicando) o
     * revertidos (si estaba revirtiendo).
     */
    private static function changedItems(CorporatePopulationUpdate $update): int
    {
        $query = Item::query()->where('corporate_population_update_id', $update->getKey());

        return $update->status === CorporatePopulationUpdate::STATUS_APPLYING
            ? $query->where('apply_status', '<>', Item::APPLY_PENDING)->count()
            : $query->where('apply_status', Item::APPLY_REVERTED)->count();
    }
}
