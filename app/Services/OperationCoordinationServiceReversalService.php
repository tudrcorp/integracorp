<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Support\SecurityAudit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class OperationCoordinationServiceReversalService
{
    public const ALLOWED_STATUS = 'PENDIENTE';

    public const OBSERVATION_PREFIX = 'Reverso de servicio de coordinación';

    /**
     * @param  Collection<int, OperationCoordinationService>|iterable<OperationCoordinationService>  $services
     * @return array{reversed_count: int, service_ids: list<int>}
     *
     * @throws InvalidArgumentException|Throwable
     */
    public function reverseMany(iterable $services, string $reversalNote): array
    {
        $note = trim($reversalNote);

        if (mb_strlen($note) < 10) {
            throw new InvalidArgumentException('La observación del reverso debe tener al menos 10 caracteres.');
        }

        $records = Collection::make($services)
            ->filter(fn (mixed $service): bool => $service instanceof OperationCoordinationService)
            ->values();

        if ($records->isEmpty()) {
            throw new InvalidArgumentException('Debe seleccionar al menos un servicio para reversar.');
        }

        $nonPending = $records->filter(
            fn (OperationCoordinationService $service): bool => ! $this->statusIsReversible((string) ($service->status ?? ''))
        );

        if ($nonPending->isNotEmpty()) {
            $examples = $nonPending
                ->take(3)
                ->map(function (OperationCoordinationService $service): string {
                    $reference = trim((string) ($service->reference_number ?? ''));
                    $label = $reference !== '' ? $reference : '#'.$service->id;
                    $status = mb_strtoupper(trim((string) ($service->status ?? 'SIN ESTATUS')));

                    return $label.' ('.$status.')';
                })
                ->implode(', ');

            throw new InvalidArgumentException(
                'El reverso no se puede ejecutar porque en la lista seleccionada existe al menos un servicio en gestión o con estatus distinto a PENDIENTE. '
                .'Ejemplos: '.$examples.'.'
            );
        }

        $user = Auth::user();
        $userId = $user?->id;
        $userName = (string) ($user?->name ?? 'SISTEMA');
        $serviceIds = $records->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all();

        DB::transaction(function () use ($records, $note, $userId, $userName, $serviceIds): void {
            SecurityAudit::log(
                'AUDIT_OPERATIONS_COORDINATION_SERVICE_BULK_REVERSAL_STARTED',
                'operations.coordination-services.bulk-reverse',
                [
                    'operation_coordination_service_ids' => $serviceIds,
                    'services_count' => count($serviceIds),
                    'reversal_note' => $note,
                    'reversed_by' => $userName,
                ]
            );

            foreach ($records as $service) {
                $this->reversePendingService($service, $note, $userId, $userName);
            }

            SecurityAudit::log(
                'AUDIT_OPERATIONS_COORDINATION_SERVICE_BULK_REVERSED',
                'operations.coordination-services.bulk-reverse',
                [
                    'operation_coordination_service_ids' => $serviceIds,
                    'services_count' => count($serviceIds),
                    'reversal_note' => $note,
                    'reversed_by' => $userName,
                ]
            );
        });

        return [
            'reversed_count' => count($serviceIds),
            'service_ids' => $serviceIds,
        ];
    }

    public function statusIsReversible(string $status): bool
    {
        return mb_strtoupper(trim($status)) === self::ALLOWED_STATUS;
    }

    public function buildBitacoraDescription(OperationCoordinationService $service, string $reversalNote): string
    {
        $serviceName = trim((string) ($service->specific_service ?: $service->servicie ?: 'NO ESPECIFICADO'));
        $reference = trim((string) ($service->reference_number ?? ''));
        $patient = trim((string) ($service->patient ?? ''));

        return self::OBSERVATION_PREFIX."\n"
            .'Servicio ID: '.$service->id."\n"
            .'Servicio: '.$serviceName."\n"
            .($reference !== '' ? 'Referencia: '.$reference."\n" : '')
            .($patient !== '' ? 'Paciente: '.$patient."\n" : '')
            .'Motivo: '.trim($reversalNote);
    }

    private function reversePendingService(
        OperationCoordinationService $service,
        string $reversalNote,
        int|string|null $userId,
        string $userName,
    ): void {
        $service->refresh();

        if (! $this->statusIsReversible((string) ($service->status ?? ''))) {
            throw new InvalidArgumentException(
                'El servicio #'.$service->id.' ya no está en estatus PENDIENTE y no puede reversarse.'
            );
        }

        $bitacoraDescription = $this->buildBitacoraDescription($service, $reversalNote);

        SecurityAudit::log(
            'AUDIT_OPERATIONS_COORDINATION_SERVICE_REVERSAL_STARTED',
            'operations.coordination-services.reverse',
            [
                'operation_coordination_service_id' => (int) $service->id,
                'telemedicine_case_id' => $service->telemedicine_case_id,
                'reference_number' => $service->reference_number,
                'status' => $service->status,
                'reversal_note' => $reversalNote,
                'reversed_by' => $userName,
            ]
        );

        if (filled($service->telemedicine_case_id)) {
            ObservationCase::query()->create([
                'telemedicine_case_id' => $service->telemedicine_case_id,
                'description' => $bitacoraDescription,
                'created_by' => $userId !== null ? (string) $userId : null,
            ]);
        }

        $payload = [
            'operation_coordination_service_id' => (int) $service->id,
            'telemedicine_case_id' => $service->telemedicine_case_id,
            'reference_number' => $service->reference_number,
            'status' => $service->status,
            'reversal_note' => $reversalNote,
            'reversed_by' => $userName,
        ];

        $service->delete();

        SecurityAudit::log(
            'AUDIT_OPERATIONS_COORDINATION_SERVICE_REVERSED',
            'operations.coordination-services.reverse',
            $payload
        );
    }
}
