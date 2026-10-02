<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationCoordinationService;
use App\Models\User;
use App\Support\Telemedicine\TelemedicineMedicationCoverage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Visibilidad y gestión de coordinaciones/ítems para proveedor vs TDG.
 *
 * Cada ítem tiene un solo responsable, nunca dos ({@see itemOwner()}):
 *
 * - Ítem cubierto (cualquier categoría): lo gestiona quien gestiona el servicio
 *   (`managed_by`). TDG si es `TDG`; si no, el proveedor del servicio. Incluye el
 *   medicamento manual que el médico del proveedor marcó como cubierto.
 * - Ítem no cubierto: lo gestiona TDG, salvo que TDG haya asignado el servicio al
 *   proveedor («Asignar a proveedor»).
 *
 * Que TDG vuelva a gestionar un servicio de proveedor no es un permiso implícito:
 * es la acción explícita y auditada {@see TakeCoordinationServiceManagementByTdg}.
 */
final class CoordinationServiceAccess
{
    public const OWNER_TDG = 'TDG';

    public const OWNER_SUPPLIER = 'SUPPLIER';

    public static function isAssignedToSupplierByTdg(OperationCoordinationService $record): bool
    {
        return (bool) $record->assigned_to_supplier_by_tdg;
    }

    /**
     * @deprecated La regla de cobertura ya no depende de la categoría: un ítem cubierto
     *             de cualquier categoría lo gestiona quien gestiona el servicio.
     */
    public static function isCoveredMedicationOrLabCategory(string $category): bool
    {
        return in_array($category, ['Medicamento', 'Laboratorio'], true);
    }

    /**
     * Responsable de un ítem: {@see OWNER_TDG} o {@see OWNER_SUPPLIER}.
     * Un servicio sin proveedor siempre queda en TDG: nadie más podría gestionarlo.
     */
    public static function itemOwner(OperationCoordinationService $record, ?bool $coverage): string
    {
        if (! filled($record->supplier_id)) {
            return self::OWNER_TDG;
        }

        if ($coverage === true) {
            return CoordinationServiceItemsManager::coordinationIsManagedByTdg($record)
                ? self::OWNER_TDG
                : self::OWNER_SUPPLIER;
        }

        return self::isAssignedToSupplierByTdg($record) ? self::OWNER_SUPPLIER : self::OWNER_TDG;
    }

    /**
     * Si la coordinación tiene al menos un ítem cubierto (cualquier categoría).
     */
    public static function coordinationHasCoveredItem(OperationCoordinationService $record): bool
    {
        if (TelemedicineMedicationCoverage::whereCovered($record->telemedicinePatientMedications()->getQuery())->exists()) {
            return true;
        }

        foreach (['telemedicinePatientLabs', 'telemedicinePatientStudies', 'telemedicinePatientSpecialties'] as $relation) {
            if (self::whereTypeIsCovered($record->{$relation}()->getQuery())->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @deprecated Use {@see coordinationHasCoveredItem()}: estudios y especialistas cubiertos también cuentan.
     */
    public static function coordinationHasCoveredMedicationOrLab(OperationCoordinationService $record): bool
    {
        return self::coordinationHasCoveredItem($record);
    }

    /**
     * Versión en memoria de {@see applyProviderCoordinationVisibilityScope()}: misma regla.
     */
    public static function providerCanSeeCoordination(OperationCoordinationService $record, int $supplierId): bool
    {
        if ((int) $record->supplier_id !== $supplierId) {
            return false;
        }

        if (self::isAssignedToSupplierByTdg($record)) {
            return true;
        }

        return self::coordinationHasCoveredItem($record);
    }

    /**
     * Restringe el listado de proveedores a sus coordinaciones con algún ítem
     * cubierto (cualquier categoría) o asignadas explícitamente por TDG.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function applyProviderCoordinationVisibilityScope(Builder $query, int $supplierId): Builder
    {
        return $query
            ->where('supplier_id', $supplierId)
            ->where(function (Builder $outer): void {
                $outer
                    ->where('assigned_to_supplier_by_tdg', true)
                    /*
                     * Misma regla de cobertura que la etiqueta CUB y la gestión por ítem:
                     * incluye el medicamento manual que el médico del proveedor marcó como cubierto.
                     */
                    ->orWhereHas('telemedicinePatientMedications', fn (Builder $medications): Builder => TelemedicineMedicationCoverage::whereCovered($medications))
                    ->orWhereHas('telemedicinePatientLabs', fn (Builder $labs): Builder => self::whereTypeIsCovered($labs))
                    ->orWhereHas('telemedicinePatientStudies', fn (Builder $studies): Builder => self::whereTypeIsCovered($studies))
                    ->orWhereHas('telemedicinePatientSpecialties', fn (Builder $specialties): Builder => self::whereTypeIsCovered($specialties));
            });
    }

    /**
     * Laboratorios, estudios y especialistas guardan su cobertura en `type`.
     */
    public static function whereTypeIsCovered(Builder $items): Builder
    {
        return $items->whereRaw('UPPER(TRIM(type)) = ?', ['CUBIERTO']);
    }

    public static function itemIsVisibleToUser(
        OperationCoordinationService $record,
        string $category,
        ?bool $coverage,
        ?User $user = null,
    ): bool {
        $user ??= Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        $supplierId = self::supplierIdOf($user);

        if ($supplierId === null) {
            return true;
        }

        if ((int) $record->supplier_id !== $supplierId) {
            return false;
        }

        return $coverage === true || self::isAssignedToSupplierByTdg($record);
    }

    /**
     * El usuario gestiona el ítem solo si es su responsable ({@see itemOwner()}):
     * TDG (o un usuario interno sin proveedor) gestiona lo que es de TDG; un
     * analista de proveedor, lo que es del proveedor y solo de su propio proveedor.
     *
     * `$category` e `$isCoveredWithoutInventory` se conservan por compatibilidad:
     * la regla ya no distingue categorías ni el origen de la cobertura.
     */
    public static function itemIsManageableByUser(
        OperationCoordinationService $record,
        string $category,
        ?bool $coverage,
        ?User $user = null,
        bool $isCoveredWithoutInventory = false,
    ): bool {
        $user ??= Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        $owner = self::itemOwner($record, $coverage);
        $supplierId = self::supplierIdOf($user);

        if ($supplierId === null) {
            return $owner === self::OWNER_TDG;
        }

        return $owner === self::OWNER_SUPPLIER && (int) $record->supplier_id === $supplierId;
    }

    /**
     * @deprecated Prefer {@see itemIsManageableByUser()}
     */
    public static function coveredItemIsManageableByTdg(
        OperationCoordinationService $record,
        string $category,
        ?bool $coverage,
    ): bool {
        return self::itemIsManageableByUser($record, $category, $coverage);
    }

    /**
     * Proveedor del usuario evaluado, no necesariamente el de la sesión.
     */
    private static function supplierIdOf(User $user): ?int
    {
        return filled($user->supplier_id) ? (int) $user->supplier_id : null;
    }
}
