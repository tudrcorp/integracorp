<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OperationInventory;
use App\Models\OperationInventoryMovement;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicinePatient;
use App\Support\Telemedicine\TelemedicineMedicationInventoryOptions;
use App\Support\Telemedicine\TelemedicineSupplyInventoryOptions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Registra los insumos médicos que el doctor declara haber consumido en la
 * consulta o el seguimiento.
 *
 * Sigue la misma regla que los medicamentos: solo descuenta existencias cuando el
 * médico es TDG y el caso pertenece a un almacén mapeado. Para médicos de
 * proveedor deja el movimiento como constancia, sin tocar el inventario de TDG.
 */
final class TelemedicineSupplyConsumptionRecorder
{
    /** Movimiento de consumo declarado que no descuenta existencias. */
    public const STATUS_DECLARED = 'DECLARADO';

    public const STATUS_DISPATCHED = 'DESPACHADO';

    public function __construct(private readonly TelemedicineMedicationInventoryDeductor $deductor) {}

    /**
     * @param  mixed  $rows  Filas crudas del repetidor del formulario.
     * @return array{recorded: int, deducted: int, failures: list<string>}
     */
    public function record(TelemedicineConsultationPatient $consultation, mixed $rows): array
    {
        $normalized = TelemedicineSupplyInventoryOptions::normalizeRows($rows);

        if ($normalized === []) {
            return ['recorded' => 0, 'deducted' => 0, 'failures' => []];
        }

        $case = TelemedicineCase::query()
            ->with('telemedicineDoctor')
            ->find($consultation->telemedicine_case_id);
        $doctor = TelemedicineDoctor::query()->find($consultation->telemedicine_doctor_id);
        $patient = TelemedicinePatient::query()->find($consultation->telemedicine_patient_id);

        $shouldDeduct = TelemedicineMedicationInventoryOptions::shouldDeductInventory($doctor, $case);
        $alreadyRecorded = $this->alreadyRecordedQuantities($consultation);

        $recorded = 0;
        $deducted = 0;
        $failures = [];

        foreach ($normalized as $row) {
            $inventoryId = $row['operation_inventory_id'];

            // Idempotencia: al reeditar la consulta solo se registra el incremento,
            // para no descontar dos veces el mismo consumo.
            $quantity = $row['quantity'] - (int) ($alreadyRecorded[$inventoryId] ?? 0);

            if ($quantity < 1) {
                continue;
            }

            try {
                $movement = DB::transaction(function () use ($shouldDeduct, $inventoryId, $quantity, $consultation, $case, $doctor, $patient): ?OperationInventoryMovement {
                    if ($shouldDeduct) {
                        return $this->deductor->deductIfApplicable(
                            $inventoryId,
                            $consultation,
                            $case,
                            $doctor,
                            $patient,
                            $quantity,
                            TelemedicineMedicationInventoryDeductor::MOVEMENT_TYPE_SUPPLY,
                            'insumo médico',
                        );
                    }

                    return $this->recordWithoutDeduction($inventoryId, $quantity, $consultation, $patient);
                });

                if ($movement !== null) {
                    $recorded++;

                    if ($shouldDeduct) {
                        $deducted++;
                    }
                }
            } catch (Throwable $exception) {
                $failures[] = $this->failureMessage($inventoryId, $exception);
            }
        }

        return ['recorded' => $recorded, 'deducted' => $deducted, 'failures' => $failures];
    }

    /**
     * Cantidad de cada insumo ya registrada para esta consulta.
     *
     * @return array<int, int>
     */
    private function alreadyRecordedQuantities(TelemedicineConsultationPatient $consultation): array
    {
        if (! filled($consultation->id)) {
            return [];
        }

        return OperationInventoryMovement::query()
            ->where('telemedicine_consultation_id', $consultation->id)
            ->where('type', TelemedicineMedicationInventoryDeductor::MOVEMENT_TYPE_SUPPLY)
            ->groupBy('operation_inventory_id')
            ->selectRaw('operation_inventory_id, SUM(quantity) as total_quantity')
            ->pluck('total_quantity', 'operation_inventory_id')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /**
     * Registra el consumo y avisa al médico solo si algo falló: el guardado de la
     * consulta ya emite su propia notificación de éxito.
     *
     * @param  mixed  $rows  Filas crudas del repetidor del formulario.
     */
    public function recordAndNotify(TelemedicineConsultationPatient $consultation, mixed $rows): void
    {
        try {
            $result = $this->record($consultation, $rows);
        } catch (Throwable $exception) {
            Log::error('Error al registrar el consumo de insumos médicos: '.$exception->getMessage(), [
                'telemedicine_consultation_id' => $consultation->id,
                'exception' => $exception,
            ]);

            Notification::make()
                ->title('No se registró el consumo de insumos')
                ->body('La consulta se guardó, pero no se pudo registrar el consumo de insumos médicos. Avise a Operaciones.')
                ->danger()
                ->send();

            return;
        }

        if ($result['failures'] === []) {
            return;
        }

        Notification::make()
            ->title('Algunos insumos no se registraron')
            ->body('La consulta se guardó. No se pudo registrar: '.implode(' · ', $result['failures']))
            ->warning()
            ->persistent()
            ->send();
    }

    /**
     * Constancia del consumo sin alterar existencias (médico de proveedor).
     */
    private function recordWithoutDeduction(
        int $inventoryId,
        int $quantity,
        TelemedicineConsultationPatient $consultation,
        ?TelemedicinePatient $patient,
    ): ?OperationInventoryMovement {
        $inventory = OperationInventory::query()->find($inventoryId);

        if ($inventory === null) {
            return null;
        }

        return OperationInventoryMovement::query()->create([
            'operation_inventory_id' => $inventory->id,
            'telemedicine_patient_id' => $consultation->telemedicine_patient_id,
            'telemedicine_case_id' => $consultation->telemedicine_case_id,
            'telemedicine_consultation_id' => $consultation->id,
            'telemedicine_doctor_id' => $consultation->telemedicine_doctor_id,
            'business_unit_id' => (int) ($patient?->business_unit_id ?? 0),
            'business_line_id' => (int) ($patient?->business_line_id ?? 0),
            'quantity' => $quantity,
            'unit' => (string) ($inventory->unit ?: 'UND'),
            'type' => TelemedicineMedicationInventoryDeductor::MOVEMENT_TYPE_SUPPLY,
            'status' => self::STATUS_DECLARED,
            'created_by' => Auth::user()?->name ?? 'system',
        ]);
    }

    private function failureMessage(int $inventoryId, Throwable $exception): string
    {
        $name = (string) (OperationInventory::query()->whereKey($inventoryId)->value('name') ?? 'Insumo #'.$inventoryId);

        return $name.': '.$exception->getMessage();
    }
}
