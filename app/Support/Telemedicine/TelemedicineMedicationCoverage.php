<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\OperationInventory;
use App\Models\TelemedicinePatientMedications;

final class TelemedicineMedicationCoverage
{
    /**
     * Si hay ítem de inventario, la cobertura proviene del inventario.
     * Los medicamentos ingresados manualmente (sin inventario) se consideran no cubiertos.
     */
    public static function isCovered(TelemedicinePatientMedications $record): bool
    {
        if (! self::hasLinkedInventory($record)) {
            return false;
        }

        if ($record->operationInventory) {
            return (bool) ($record->operationInventory->is_covered ?? false);
        }

        return (bool) ($record->is_covered ?? false);
    }

    public static function hasLinkedInventory(TelemedicinePatientMedications $record): bool
    {
        return filled($record->operation_inventory_id);
    }

    public static function isManualMedication(TelemedicinePatientMedications $record): bool
    {
        return ! self::hasLinkedInventory($record);
    }

    public static function coverageForPersist(?int $operationInventoryId): bool
    {
        if (! filled($operationInventoryId)) {
            return false;
        }

        $inventory = OperationInventory::query()->find($operationInventoryId);

        return (bool) ($inventory?->is_covered ?? false);
    }
}
