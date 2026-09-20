<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\OperationInventoryMovement;
use App\Models\OperationServiceOrder;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDocument;
use App\Support\ClinicalEntitlements\ClinicalUsageLedger;
use App\Support\SecurityAudit;
use App\Support\Telemedicine\Scopes\HideDeletedTelemedicineCaseServiceOrdersScope;
use App\Support\Telemedicine\Scopes\HideDeletedTelemedicineCasesScope;
use App\Support\Telemedicine\Scopes\HideDeletedTelemedicineCaseTracesScope;
use App\Support\Telemedicine\TelemedicineCaseDeletion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Eliminación y restauración lógica de un caso de telemedicina.
 *
 * El caso no se borra: cambia a `status = ELIMINADO` y desaparece de la UI junto
 * con sus trazas, gracias a los scopes globales. Nada se pierde en base de
 * datos, así que la auditoría, los reportes históricos ya emitidos y las claves
 * foráneas siguen cuadrando, y el caso puede volver exactamente a su estatus
 * anterior.
 */
final class TelemedicineCaseLogicalDeletionService
{
    private const MINIMUM_REASON_LENGTH = 10;

    /**
     * @return array<string, mixed>
     *
     * @throws Throwable
     */
    public function delete(TelemedicineCase $case, string $reason): array
    {
        $reason = $this->validatedReason($reason);

        if (TelemedicineCaseDeletion::isDeleted($case)) {
            throw new InvalidArgumentException('El caso '.$case->code.' ya está eliminado.');
        }

        if (! TelemedicineCaseDeletion::canBeDeleted($case)) {
            throw new InvalidArgumentException(
                TelemedicineCaseDeletion::blockedReason($case) ?? 'El caso no puede eliminarse.'
            );
        }

        $user = Auth::user();
        $previousStatus = (string) $case->status;
        $summary = $this->traceSummary($case);

        $payload = [
            'telemedicine_case_id' => (int) $case->id,
            'telemedicine_case_code' => (string) $case->code,
            'telemedicine_patient_id' => $case->telemedicine_patient_id,
            'patient_name' => $case->patient_name,
            'previous_status' => $previousStatus,
            'deletion_reason' => $reason,
            'deleted_by' => (string) ($user?->name ?? 'SISTEMA'),
            'deleted_by_user_id' => $user?->id,
            'traces' => $summary,
        ];

        DB::transaction(function () use ($case, $previousStatus, $reason, $user, $payload): void {
            SecurityAudit::log(
                'AUDIT_TELEMEDICINE_CASE_LOGICAL_DELETION_STARTED',
                'operations.coordination-services.delete-case',
                $payload,
            );

            ObservationCase::query()->create([
                'telemedicine_case_id' => (int) $case->id,
                'description' => $this->deletionBitacoraDescription($previousStatus, $reason, (string) ($user?->name ?? 'SISTEMA')),
                'created_by' => self::bitacoraAuthor($user?->id),
            ]);

            /** El caso deja de existir para el sistema: su consumo de cupos se devuelve al afiliado. */
            ClinicalUsageLedger::reverseForCase((int) $case->id);

            $case->forceFill([
                'deletion_status_before' => $previousStatus,
                'status' => TelemedicineCaseDeletion::STATUS,
                'deletion_reason' => $reason,
                'deleted_by_user_id' => $user?->id,
                'deleted_by_name' => (string) ($user?->name ?? 'SISTEMA'),
                'logically_deleted_at' => now(),
            ])->save();

            SecurityAudit::log(
                'AUDIT_TELEMEDICINE_CASE_LOGICALLY_DELETED',
                'operations.coordination-services.delete-case',
                $payload,
            );
        });

        return $payload;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Throwable
     */
    public function restore(TelemedicineCase $case, string $reason): array
    {
        $reason = $this->validatedReason($reason);

        if (! TelemedicineCaseDeletion::isDeleted($case)) {
            throw new InvalidArgumentException('El caso '.$case->code.' no está eliminado.');
        }

        $user = Auth::user();
        $restoredStatus = $this->statusToRestore($case);

        $payload = [
            'telemedicine_case_id' => (int) $case->id,
            'telemedicine_case_code' => (string) $case->code,
            'telemedicine_patient_id' => $case->telemedicine_patient_id,
            'restored_status' => $restoredStatus,
            'restoration_reason' => $reason,
            'deleted_by' => $case->deleted_by_name,
            'deletion_reason' => $case->deletion_reason,
            'restored_by' => (string) ($user?->name ?? 'SISTEMA'),
            'restored_by_user_id' => $user?->id,
        ];

        DB::transaction(function () use ($case, $restoredStatus, $reason, $user, $payload): void {
            $case->forceFill([
                'status' => $restoredStatus,
                'deletion_status_before' => null,
                'deletion_reason' => null,
                'deleted_by_user_id' => null,
                'deleted_by_name' => null,
                'logically_deleted_at' => null,
            ])->save();

            ObservationCase::query()->create([
                'telemedicine_case_id' => (int) $case->id,
                'description' => $this->restorationBitacoraDescription($restoredStatus, $reason, (string) ($user?->name ?? 'SISTEMA')),
                'created_by' => self::bitacoraAuthor($user?->id),
            ]);

            SecurityAudit::log(
                'AUDIT_TELEMEDICINE_CASE_LOGICALLY_RESTORED',
                'operations.coordination-services.restore-case',
                $payload,
            );
        });

        return $payload;
    }

    /**
     * Qué arrastra el caso, para advertirlo antes de eliminar.
     *
     * Los movimientos de inventario se cuentan pero **no** se ocultan: el
     * medicamento salió físicamente del almacén y el stock debe seguir
     * cuadrando con la existencia real.
     *
     * @return array{services: int, service_orders: int, billed_service_orders: int, consultations: int, documents: int, inventory_movements: int}
     */
    public function traceSummary(TelemedicineCase $case): array
    {
        $caseId = (int) $case->id;

        $serviceIds = OperationCoordinationService::query()
            ->withoutGlobalScope(HideDeletedTelemedicineCaseTracesScope::class)
            ->where('telemedicine_case_id', $caseId)
            ->pluck('id')
            ->all();

        $serviceOrders = OperationServiceOrder::query()
            ->withoutGlobalScope(HideDeletedTelemedicineCaseServiceOrdersScope::class)
            ->whereIn('operation_coordination_service_id', $serviceIds);

        return [
            'services' => count($serviceIds),
            'service_orders' => (clone $serviceOrders)->count(),
            'billed_service_orders' => (clone $serviceOrders)->whereNotNull('invoice_number')->count(),
            'consultations' => TelemedicineConsultationPatient::query()
                ->withoutGlobalScope(HideDeletedTelemedicineCaseTracesScope::class)
                ->where('telemedicine_case_id', $caseId)
                ->count(),
            'documents' => TelemedicineDocument::query()
                ->withoutGlobalScope(HideDeletedTelemedicineCaseTracesScope::class)
                ->where('telemedicine_case_id', $caseId)
                ->count(),
            'inventory_movements' => OperationInventoryMovement::query()
                ->where('telemedicine_case_id', $caseId)
                ->count(),
        ];
    }

    /**
     * Carga un caso saltando el scope que oculta los eliminados.
     */
    public static function findIncludingDeleted(int $caseId): ?TelemedicineCase
    {
        return TelemedicineCase::query()
            ->withoutGlobalScope(HideDeletedTelemedicineCasesScope::class)
            ->find($caseId);
    }

    /**
     * `observation_cases.created_by` no admite nulos: fuera de una sesión
     * autenticada (consola, jobs) la bitácora se firma como SISTEMA.
     */
    private static function bitacoraAuthor(int|string|null $userId): string
    {
        return $userId !== null ? (string) $userId : 'SISTEMA';
    }

    private function statusToRestore(TelemedicineCase $case): string
    {
        $previous = trim((string) $case->deletion_status_before);

        if ($previous === '' || TelemedicineCaseDeletion::statusIsDeleted($previous)) {
            return 'EN SEGUIMIENTO';
        }

        return $previous;
    }

    private function validatedReason(string $reason): string
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < self::MINIMUM_REASON_LENGTH) {
            throw new InvalidArgumentException('Debe indicar el motivo con al menos '.self::MINIMUM_REASON_LENGTH.' caracteres.');
        }

        return $reason;
    }

    private function deletionBitacoraDescription(string $previousStatus, string $reason, string $userName): string
    {
        return 'CASO ELIMINADO · Estatus anterior: '.$previousStatus
            .' · Eliminado por: '.$userName
            .' · Fecha: '.now()->timezone((string) config('app.timezone'))->format('d/m/Y H:i:s')
            .' · Motivo: '.$reason;
    }

    private function restorationBitacoraDescription(string $restoredStatus, string $reason, string $userName): string
    {
        return 'CASO RESTAURADO · Estatus restituido: '.$restoredStatus
            .' · Restaurado por: '.$userName
            .' · Fecha: '.now()->timezone((string) config('app.timezone'))->format('d/m/Y H:i:s')
            .' · Motivo: '.$reason;
    }
}
