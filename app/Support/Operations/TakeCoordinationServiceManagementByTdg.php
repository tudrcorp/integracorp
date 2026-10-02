<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\Supplier;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * TDG toma la gestión de una coordinación que hoy gestiona un proveedor (proveedor → TDG).
 *
 * Es el único camino para que TDG gestione ítems de un servicio de proveedor: no
 * hay permiso de respaldo implícito ({@see CoordinationServiceAccess::itemOwner()}).
 * Deja `managed_by = TDG` y retira la asignación al proveedor, así todos los ítems
 * pasan a TDG y el proveedor deja de poder gestionarlos. Queda en observaciones,
 * en la bitácora del caso y en la auditoría de seguridad.
 */
final class TakeCoordinationServiceManagementByTdg
{
    public const OBSERVATION_PREFIX = 'Toma de gestión de la coordinación por TDG.';

    public const REASON_MIN_LENGTH = 10;

    /**
     * @var list<string>
     */
    public const CLOSED_STATUSES = ['FINALIZADO', 'CANCELADA', 'CANCELADO', ReassignAmbulanceCoordinationToTdgDoctor::STATUS_REASSIGNED_TO_TDG];

    /**
     * Algún ítem lo gestiona el proveedor: o el servicio es suyo (`managed_by` no es TDG)
     * o TDG le asignó los no cubiertos.
     */
    public static function isManagedBySupplier(OperationCoordinationService $record): bool
    {
        if (! filled($record->supplier_id)) {
            return false;
        }

        return ! CoordinationServiceItemsManager::coordinationIsManagedByTdg($record)
            || CoordinationServiceAccess::isAssignedToSupplierByTdg($record);
    }

    public static function canBeTakenOver(OperationCoordinationService $record): bool
    {
        return self::isManagedBySupplier($record)
            && ! in_array(mb_strtoupper(trim((string) $record->status)), self::CLOSED_STATUSES, true);
    }

    public static function buildBitacoraDescription(string $previousManager, string $reason): string
    {
        return self::OBSERVATION_PREFIX
            ."\n".'Gestionaba: '.$previousManager
            ."\n".'Motivo: '.trim($reason);
    }

    /**
     * @throws InvalidArgumentException con un mensaje en español apto para mostrar al analista
     */
    public static function execute(OperationCoordinationService $coordination, string $reason, ?User $user = null): OperationCoordinationService
    {
        $user ??= Auth::user();

        if (! $user instanceof User || filled($user->supplier_id) || $user->isProveedorAmd()) {
            throw new InvalidArgumentException('Solo un analista TDG puede tomar la gestión de una coordinación.');
        }

        $reason = Str::squish($reason);

        if (mb_strlen($reason) < self::REASON_MIN_LENGTH) {
            throw new InvalidArgumentException('El motivo debe tener al menos '.self::REASON_MIN_LENGTH.' caracteres.');
        }

        $userName = filled($user->name) ? (string) $user->name : 'SISTEMA';

        return DB::transaction(function () use ($coordination, $reason, $user, $userName): OperationCoordinationService {
            /** @var OperationCoordinationService|null $locked */
            $locked = OperationCoordinationService::query()->whereKey($coordination->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                throw new InvalidArgumentException('La coordinación ya no existe.');
            }

            if (! self::isManagedBySupplier($locked)) {
                throw new InvalidArgumentException('Esta coordinación ya la gestiona TDG.');
            }

            if (! self::canBeTakenOver($locked)) {
                throw new InvalidArgumentException('No se puede tomar la gestión de una coordinación '.mb_strtoupper(trim((string) $locked->status)).'.');
            }

            $itemsInManagement = self::itemsInManagementCount($locked);

            if ($itemsInManagement > 0) {
                throw new InvalidArgumentException($itemsInManagement === 1
                    ? 'El proveedor tiene 1 ítem EN GESTION. Finalícelo o reviértalo antes de que TDG tome la gestión, para no duplicar órdenes.'
                    : "El proveedor tiene {$itemsInManagement} ítems EN GESTION. Finalícelos o reviértalos antes de que TDG tome la gestión, para no duplicar órdenes.");
            }

            $previousManager = self::previousManagerLabel($locked);
            $bitacoraDescription = self::buildBitacoraDescription($previousManager, $reason);
            $previousObservations = trim((string) ($locked->observations ?? ''));
            $previous = [
                'managed_by' => $locked->managed_by,
                'assigned_to_supplier_by_tdg' => (bool) $locked->assigned_to_supplier_by_tdg,
            ];

            $locked->managed_by = CoordinationServiceAccess::OWNER_TDG;
            $locked->assigned_to_supplier_by_tdg = false;
            $locked->assigned_to_supplier_by_tdg_at = null;
            $locked->assigned_to_supplier_by_tdg_by = null;
            $locked->observations = $previousObservations !== ''
                ? $previousObservations."\n\n".$bitacoraDescription
                : $bitacoraDescription;
            $locked->updated_by = $userName;
            $locked->save();

            if (filled($locked->telemedicine_case_id)) {
                ObservationCase::query()->create([
                    'telemedicine_case_id' => $locked->telemedicine_case_id,
                    'description' => $bitacoraDescription,
                    'created_by' => (string) $user->id,
                ]);
            }

            SecurityAudit::log('AUDIT_OPERATIONS_COORDINATION_TAKEN_OVER_BY_TDG', 'operations.coordination-services.take-over', [
                'operation_coordination_service_id' => $locked->id,
                'reference_number' => $locked->reference_number,
                'telemedicine_case_id' => $locked->telemedicine_case_id,
                'supplier_id' => $locked->supplier_id,
                'previous' => $previous,
                'reason' => $reason,
            ]);

            return $locked;
        });
    }

    private static function itemsInManagementCount(OperationCoordinationService $record): int
    {
        $count = 0;

        foreach (CoordinationServiceItemsManager::CLINICAL_ITEM_RELATIONS as $relation) {
            $count += $record->{$relation}()->whereRaw('UPPER(TRIM(status)) = ?', ['EN GESTION'])->count();
        }

        return $count;
    }

    private static function previousManagerLabel(OperationCoordinationService $record): string
    {
        $supplier = Supplier::query()->whereKey($record->supplier_id)->first(['id', 'name', 'integracorp_alias']);
        $supplierName = Str::squish((string) ($supplier?->integracorp_alias ?: $supplier?->name ?: 'Proveedor #'.$record->supplier_id));

        if (CoordinationServiceItemsManager::coordinationIsManagedByTdg($record)) {
            return $supplierName.' (asignado por TDG)';
        }

        return $supplierName;
    }
}
