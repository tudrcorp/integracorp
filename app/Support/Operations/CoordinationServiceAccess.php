<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationCoordinationService;
use App\Models\User;
use App\Support\Filament\Operations\OperationsSupplierScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Visibilidad y gestión de coordinaciones/ítems para proveedor vs TDG.
 */
final class CoordinationServiceAccess
{
    public static function isAssignedToSupplierByTdg(OperationCoordinationService $record): bool
    {
        return (bool) $record->assigned_to_supplier_by_tdg;
    }

    public static function isCoveredMedicationOrLabCategory(string $category): bool
    {
        return in_array($category, ['Medicamento', 'Laboratorio'], true);
    }

    public static function coordinationHasCoveredMedicationOrLab(OperationCoordinationService $record): bool
    {
        $hasCoveredMedication = $record->telemedicinePatientMedications()
            ->whereHas('operationInventory', function (Builder $inventory): void {
                $inventory->where('is_covered', true);
            })
            ->exists();

        if ($hasCoveredMedication) {
            return true;
        }

        return $record->telemedicinePatientLabs()
            ->whereRaw('UPPER(TRIM(type)) = ?', ['CUBIERTO'])
            ->exists();
    }

    public static function providerCanSeeCoordination(OperationCoordinationService $record, int $supplierId): bool
    {
        if ((int) $record->supplier_id !== $supplierId) {
            return false;
        }

        if (self::isAssignedToSupplierByTdg($record)) {
            return true;
        }

        return self::coordinationHasCoveredMedicationOrLab($record);
    }

    /**
     * Restringe el listado de proveedores a coordinaciones con med/lab cubierto
     * o asignadas explícitamente por TDG.
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
                    ->orWhereHas('telemedicinePatientMedications', function (Builder $medications): void {
                        $medications->whereHas('operationInventory', function (Builder $inventory): void {
                            $inventory->where('is_covered', true);
                        });
                    })
                    ->orWhereHas('telemedicinePatientLabs', function (Builder $labs): void {
                        $labs->whereRaw('UPPER(TRIM(type)) = ?', ['CUBIERTO']);
                    });
            });
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

        if (OperationsSupplierScope::authenticatedUserIsTdgAnalyst()) {
            return true;
        }

        if (OperationsSupplierScope::currentSupplierId() === null) {
            return true;
        }

        if (self::isCoveredMedicationOrLabCategory($category) && $coverage === true) {
            return true;
        }

        return self::isAssignedToSupplierByTdg($record);
    }

    public static function itemIsManageableByUser(
        OperationCoordinationService $record,
        string $category,
        ?bool $coverage,
        ?User $user = null,
    ): bool {
        $user ??= Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        $isCoveredMedOrLab = self::isCoveredMedicationOrLabCategory($category) && $coverage === true;
        $managedByTdg = CoordinationServiceItemsManager::coordinationIsManagedByTdg($record);

        if (OperationsSupplierScope::authenticatedUserIsTdgAnalyst()) {
            if ($isCoveredMedOrLab) {
                return $managedByTdg;
            }

            return true;
        }

        if (OperationsSupplierScope::currentSupplierId() === null) {
            if ($isCoveredMedOrLab) {
                return $managedByTdg;
            }

            return true;
        }

        if ($isCoveredMedOrLab) {
            return ! $managedByTdg;
        }

        return self::isAssignedToSupplierByTdg($record);
    }

    /**
     * Compatibilidad: TDG (o usuarios sin supplier) solo gestionan med/lab cubiertos
     * cuando managed_by = TDG. Para proveedor, la regla opuesta aplica vía
     * {@see itemIsManageableByUser()}.
     *
     * @deprecated Prefer {@see itemIsManageableByUser()}
     */
    public static function coveredItemIsManageableByTdg(
        OperationCoordinationService $record,
        string $category,
        ?bool $coverage,
    ): bool {
        return self::itemIsManageableByUser($record, $category, $coverage);
    }
}
