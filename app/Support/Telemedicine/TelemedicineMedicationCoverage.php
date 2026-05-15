<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicinePatientMedications;

final class TelemedicineMedicationCoverage
{
    /**
     * Consistente con la vista de medicamentos en coordinación: si hay ítem de inventario,
     * la cobertura proviene del inventario; si no, del campo en telemedicine_patient_medications.
     */
    public static function isCovered(TelemedicinePatientMedications $record): bool
    {
        if ($record->operation_inventory_id && $record->operationInventory) {
            return (bool) ($record->operationInventory->is_covered ?? false);
        }

        return (bool) ($record->is_covered ?? false);
    }
}
