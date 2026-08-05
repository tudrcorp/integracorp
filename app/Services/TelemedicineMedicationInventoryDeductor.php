<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OperationInventory;
use App\Models\OperationInventoryMovement;
use App\Models\OperationInventoryOutflow;
use App\Models\OperationInventoryProductStock;
use App\Models\OperationInventoryType;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicinePatient;
use App\Support\Telemedicine\TelemedicineMedicationInventoryOptions;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

final class TelemedicineMedicationInventoryDeductor
{
    public const MOVEMENT_TYPE = 'SALIDA TELEMEDICINA';

    public const OUTFLOW_TYPE = 'SALIDA TELEMEDICINA';

    public const DEFAULT_QUANTITY = 1;

    public function deductIfApplicable(
        ?int $operationInventoryId,
        TelemedicineConsultationPatient|array $consultation,
        TelemedicineCase|array|null $case = null,
        TelemedicineDoctor|array|null $doctor = null,
        TelemedicinePatient|array|null $patient = null,
        int $quantity = self::DEFAULT_QUANTITY,
    ): ?OperationInventoryMovement {
        if ($operationInventoryId === null || $quantity < 1) {
            return null;
        }

        $consultationModel = $this->resolveConsultation($consultation);
        $caseModel = $this->resolveCase($case, $consultationModel);
        $doctorModel = $this->resolveDoctor($doctor, $consultationModel);

        if (! TelemedicineMedicationInventoryOptions::shouldDeductInventory($doctorModel, $caseModel)) {
            return null;
        }

        $warehouseName = TelemedicineMedicationInventoryOptions::warehouseNameForBelongsTo($caseModel?->belongs_to);

        if ($warehouseName === null) {
            return null;
        }

        $inventory = OperationInventory::query()
            ->with(['ubicationRelation', 'product'])
            ->whereKey($operationInventoryId)
            ->lockForUpdate()
            ->first();

        if ($inventory === null) {
            throw new RuntimeException("Inventario #{$operationInventoryId} no encontrado.");
        }

        $inventoryWarehouse = mb_strtoupper(trim((string) ($inventory->ubicationRelation?->name ?? $inventory->ubication ?? '')));

        if ($inventoryWarehouse !== mb_strtoupper(trim($warehouseName))) {
            throw new RuntimeException(
                "El medicamento «{$inventory->name}» no pertenece al almacén {$warehouseName}."
            );
        }

        if ((int) $inventory->existence < $quantity) {
            throw new RuntimeException(
                "Existencia insuficiente para «{$inventory->name}». Disponible: {$inventory->existence}."
            );
        }

        $inventory->existence = (int) $inventory->existence - $quantity;
        $inventory->updated_by = Auth::user()?->name ?? 'system';
        $inventory->save();

        $productId = filled($inventory->operation_inventory_product_id)
            ? (int) $inventory->operation_inventory_product_id
            : null;
        $previousTotal = null;

        if ($productId !== null) {
            $previousTotal = (int) OperationInventoryProductStock::query()
                ->where('operation_inventory_product_id', $productId)
                ->sum('existence');
        }

        if (
            filled($inventory->operation_inventory_product_id)
            && filled($inventory->operation_inventory_ubication_id)
        ) {
            $stock = OperationInventoryProductStock::query()
                ->where('operation_inventory_product_id', $inventory->operation_inventory_product_id)
                ->where('operation_inventory_ubication_id', $inventory->operation_inventory_ubication_id)
                ->lockForUpdate()
                ->first();

            if ($stock !== null) {
                $stock->existence = max(0, (int) $stock->existence - $quantity);
                $stock->updated_by = Auth::user()?->name ?? 'system';
                $stock->save();
            }
        }

        $typeId = (int) (OperationInventoryType::query()->orderBy('id')->value('id') ?? 1);

        OperationInventoryOutflow::query()->create([
            'operation_inventory_id' => $inventory->id,
            'telemedicine_case_id' => $consultationModel->telemedicine_case_id,
            'operation_inventory_product_id' => $inventory->operation_inventory_product_id,
            'operation_inventory_ubication_id' => $inventory->operation_inventory_ubication_id,
            'operation_inventory_type_id' => $typeId,
            'quantity' => $quantity,
            'type_entry' => self::OUTFLOW_TYPE,
            'created_by' => Auth::user()?->name ?? 'system',
        ]);

        $patientModel = $this->resolvePatient($patient, $consultationModel);

        $movement = OperationInventoryMovement::query()->create([
            'operation_inventory_id' => $inventory->id,
            'telemedicine_patient_id' => $consultationModel->telemedicine_patient_id,
            'telemedicine_case_id' => $consultationModel->telemedicine_case_id,
            'telemedicine_consultation_id' => $consultationModel->id,
            'telemedicine_doctor_id' => $consultationModel->telemedicine_doctor_id,
            'business_unit_id' => (int) ($patientModel?->business_unit_id ?? 0),
            'business_line_id' => (int) ($patientModel?->business_line_id ?? 0),
            'quantity' => $quantity,
            'unit' => (string) ($inventory->unit ?: 'UND'),
            'type' => self::MOVEMENT_TYPE,
            'status' => 'DESPACHADO',
            'created_by' => Auth::user()?->name ?? 'system',
        ]);

        if ($productId !== null && $previousTotal !== null) {
            app(OperationInventoryLowStockWatcher::class)
                ->dispatchIfCrossedThreshold($productId, $previousTotal);
        }

        return $movement;
    }

    private function resolveConsultation(TelemedicineConsultationPatient|array $consultation): TelemedicineConsultationPatient
    {
        if ($consultation instanceof TelemedicineConsultationPatient) {
            return $consultation;
        }

        $model = new TelemedicineConsultationPatient;
        $model->forceFill($consultation);
        $model->id = (int) ($consultation['id'] ?? 0);

        return $model;
    }

    private function resolveCase(
        TelemedicineCase|array|null $case,
        TelemedicineConsultationPatient $consultation,
    ): ?TelemedicineCase {
        if ($case instanceof TelemedicineCase) {
            return $case;
        }

        if (is_array($case)) {
            $model = new TelemedicineCase;
            $model->forceFill($case);
            $model->id = (int) ($case['id'] ?? 0);

            return $model;
        }

        return TelemedicineCase::query()->find($consultation->telemedicine_case_id);
    }

    private function resolveDoctor(
        TelemedicineDoctor|array|null $doctor,
        TelemedicineConsultationPatient $consultation,
    ): ?TelemedicineDoctor {
        if ($doctor instanceof TelemedicineDoctor) {
            return $doctor;
        }

        if (is_array($doctor)) {
            $model = new TelemedicineDoctor;
            $model->forceFill($doctor);
            $model->id = (int) ($doctor['id'] ?? 0);

            return $model;
        }

        return TelemedicineDoctor::query()->find($consultation->telemedicine_doctor_id);
    }

    private function resolvePatient(
        TelemedicinePatient|array|null $patient,
        TelemedicineConsultationPatient $consultation,
    ): ?TelemedicinePatient {
        if ($patient instanceof TelemedicinePatient) {
            return $patient;
        }

        if (is_array($patient)) {
            $model = new TelemedicinePatient;
            $model->forceFill($patient);
            $model->id = (int) ($patient['id'] ?? 0);

            return $model;
        }

        return TelemedicinePatient::query()->find($consultation->telemedicine_patient_id);
    }
}
