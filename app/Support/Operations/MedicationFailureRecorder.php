<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationCoordinationService;
use App\Models\OperationMedicationFailure;
use App\Models\TelemedicinePatientMedications;
use App\Models\User;
use App\Support\SecurityAudit;
use App\Support\Telemedicine\TelemedicinePatientDisplayName;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Fallas de medicamentos: lo que el inventario no pudo cubrir y la empresa debe adquirir.
 *
 * Se registran al confirmar «Gestionar servicio» cuando el analista marca «Generar
 * cotización» en un medicamento NO CUBIERTO. Un medicamento recetado tiene a lo sumo una
 * falla pendiente: si ya existe, no se duplica.
 */
final class MedicationFailureRecorder
{
    public const AUDIT_RESOLVED = 'AUDIT_OPERATIONS_MEDICATION_FAILURE_RESOLVED';

    /**
     * Registra las fallas de las filas marcadas. Debe llamarse dentro de una transacción.
     *
     * @param  list<array<string, mixed>>  $rows  Filas con «Generar cotización».
     * @return list<OperationMedicationFailure>
     */
    public static function recordFromRows(OperationCoordinationService $record, array $rows, ?User $user = null): array
    {
        $user ??= Auth::user();
        $userName = filled($user?->name) ? (string) $user->name : 'SISTEMA';
        $reasons = OperationMedicationFailure::reasonOptions();
        $record->loadMissing('telemedicineCase');
        $created = [];

        foreach ($rows as $row) {
            $medicationId = MedicationInvoiceWizard::medicationIdFromKey((string) ($row['key'] ?? ''));
            $reason = (string) ($row['failure_reason'] ?? '');

            if ($medicationId <= 0) {
                continue;
            }

            if (! array_key_exists($reason, $reasons)) {
                throw new MedicationInvoiceException('Indique el motivo de «Generar cotización» de cada medicamento.');
            }

            $medication = TelemedicinePatientMedications::query()
                ->where('operation_coordination_service_id', $record->getKey())
                ->whereKey($medicationId)
                ->lockForUpdate()
                ->first();

            if (! $medication instanceof TelemedicinePatientMedications) {
                throw new MedicationInvoiceException('Uno de los medicamentos ya no pertenece a este servicio. Recargue la página.');
            }

            $alreadyPending = OperationMedicationFailure::query()
                ->where('telemedicine_patient_medication_id', $medicationId)
                ->where('status', OperationMedicationFailure::STATUS_PENDING_PURCHASE)
                ->exists();

            if ($alreadyPending) {
                continue;
            }

            $notes = trim((string) ($row['failure_notes'] ?? ''));

            $created[] = OperationMedicationFailure::query()->create([
                'telemedicine_patient_medication_id' => $medicationId,
                'operation_coordination_service_id' => $record->getKey(),
                'telemedicine_patient_id' => $medication->telemedicine_patient_id ?? $record->telemedicine_patient_id,
                'telemedicine_case_id' => $medication->telemedicine_case_id ?? $record->telemedicine_case_id,
                'medicine_name' => trim((string) $medication->medicine),
                'indications' => filled($medication->indications) ? trim((string) $medication->indications) : null,
                'patient_name' => TelemedicinePatientDisplayName::forCoordination($record) ?: null,
                'case_code' => filled($record->telemedicineCase?->code) ? mb_strtoupper((string) $record->telemedicineCase->code) : null,
                'reason' => $reason,
                'notes' => $notes !== '' ? mb_substr($notes, 0, 2000) : null,
                'status' => OperationMedicationFailure::STATUS_PENDING_PURCHASE,
                'reported_by_user_id' => $user?->getKey(),
                'reported_by_name' => $userName,
            ]);
        }

        if ($created !== []) {
            $description = 'Fallas de medicamentos registradas por '.$userName.': '
                .implode('; ', array_map(
                    fn (OperationMedicationFailure $failure): string => $failure->medicine_name.' ('.OperationMedicationFailure::reasonLabel($failure->reason).')',
                    $created,
                ))
                .'. Pasan a cotización y quedan pendientes de compra.';

            MedicationInvoiceIssuer::appendCoordinationLog($record, $description, $userName, $user?->getKey());
        }

        return $created;
    }

    /**
     * Cierra una falla pendiente como ADQUIRIDO o DESCARTADO.
     *
     * @throws MedicationInvoiceException
     */
    public static function resolve(OperationMedicationFailure $failure, string $status, string $notes, ?int $productId = null, ?User $user = null): OperationMedicationFailure
    {
        if (! in_array($status, [OperationMedicationFailure::STATUS_ACQUIRED, OperationMedicationFailure::STATUS_DISCARDED], true)) {
            throw new MedicationInvoiceException('Estado de cierre no válido.');
        }

        $notes = trim($notes);

        if (mb_strlen($notes) < 5) {
            throw new MedicationInvoiceException('Escriba una nota de al menos 5 caracteres que explique el cierre.');
        }

        $user ??= Auth::user();
        $userName = filled($user?->name) ? (string) $user->name : 'SISTEMA';

        $resolved = DB::transaction(function () use ($failure, $status, $notes, $productId, $userName): OperationMedicationFailure {
            $locked = OperationMedicationFailure::query()->whereKey($failure->getKey())->lockForUpdate()->first();

            if (! $locked instanceof OperationMedicationFailure || ! $locked->isPendingPurchase()) {
                throw new MedicationInvoiceException('La falla ya fue cerrada por otra persona. Recargue la página.');
            }

            if ($productId !== null && $productId > 0 && ! MedicationInventoryCatalog::productsById([$productId])->has($productId)) {
                throw new MedicationInvoiceException('El producto elegido ya no existe en el inventario.');
            }

            $locked->forceFill([
                'status' => $status,
                'resolved_at' => now(),
                'resolved_by_name' => $userName,
                'resolution_notes' => mb_substr($notes, 0, 2000),
                'operation_inventory_product_id' => $status === OperationMedicationFailure::STATUS_ACQUIRED && $productId !== null && $productId > 0 ? $productId : null,
            ])->save();

            return $locked;
        });

        SecurityAudit::log(self::AUDIT_RESOLVED, MedicationInvoiceIssuer::AUDIT_ROUTE, [
            'operation_medication_failure_id' => $resolved->getKey(),
            'status' => $status,
            'operation_inventory_product_id' => $resolved->operation_inventory_product_id,
        ], $user);

        return $resolved;
    }
}
