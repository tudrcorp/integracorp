<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationCoordinationService;
use App\Models\OperationServiceOrder;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

final class OperationServiceOrderCoordinationSync
{
    /**
     * Tipo de servicio de la orden → modelo clínico y columna con el nombre del ítem.
     *
     * @var array<string, array{0: class-string, 1: string}>
     */
    public const CLINICAL_ITEM_SOURCES = [
        'MEDICAMENTOS' => [TelemedicinePatientMedications::class, 'medicine'],
        'LABORATORIOS' => [TelemedicinePatientLab::class, 'laboratory'],
        'IMAGENOLOGIA' => [TelemedicinePatientStudy::class, 'study'],
        'ESPECIALISTA' => [TelemedicinePatientSpecialty::class, 'specialty'],
    ];

    public static function finalizeOrder(OperationServiceOrder $order): OperationServiceOrder
    {
        $order->update([
            'status' => 'FINALIZADO',
            'updated_by' => Auth::user()?->name,
        ]);

        $freshOrder = $order->fresh() ?? $order;

        self::finalizeClinicalItemsForOrder($freshOrder);

        return $freshOrder;
    }

    public static function cancelClinicalItemsForOrder(OperationServiceOrder $order): int
    {
        return self::applyClinicalItemStatus($order, 'CANCELADA');
    }

    public static function releaseClinicalItemsForOrder(OperationServiceOrder $order): int
    {
        return self::applyClinicalItemStatus($order, 'PENDIENTE');
    }

    public static function finalizeClinicalItemsForOrder(OperationServiceOrder $order): int
    {
        $order->loadMissing('operationServiceOrderItems');

        $coordinationId = (int) ($order->operation_coordination_service_id ?? 0);

        if ($coordinationId <= 0) {
            return 0;
        }

        /** @var list<string> $itemNames */
        $itemNames = $order->operationServiceOrderItems
            ->pluck('item_name')
            ->map(fn (mixed $name): string => self::normalizeItemName(is_string($name) ? $name : null))
            ->filter(fn (string $name): bool => $name !== '')
            ->unique()
            ->values()
            ->all();

        if ($itemNames === []) {
            return 0;
        }

        $serviceType = mb_strtoupper(trim((string) ($order->service_type ?? '')));

        $updated = match ($serviceType) {
            'MEDICAMENTOS' => self::finalizeMedications($coordinationId, $itemNames),
            'LABORATORIOS' => self::finalizeLabs($coordinationId, $itemNames),
            'IMAGENOLOGIA' => self::finalizeStudies($coordinationId, $itemNames),
            'ESPECIALISTA' => self::finalizeSpecialties($coordinationId, $itemNames),
            default => 0,
        };

        if ($updated > 0) {
            $coordination = OperationCoordinationService::query()->find($coordinationId);

            if ($coordination instanceof OperationCoordinationService) {
                self::refreshCoordinationStatus($coordination);
            }
        }

        return $updated;
    }

    public static function refreshCoordinationStatus(OperationCoordinationService $coordination): void
    {
        $items = CoordinationServiceItemsManager::associatedServiceItemsForManagement($coordination);

        if ($items->isEmpty()) {
            return;
        }

        if (! self::allItemsAreFinalized($items)) {
            return;
        }

        $coordination->update([
            'status' => 'FINALIZADO',
            'updated_by' => Auth::user()?->name,
        ]);
    }

    /**
     * @param  Collection<int, array{status: string}>  $items
     */
    public static function allItemsAreFinalized(Collection $items): bool
    {
        return $items->every(
            fn (array $item): bool => mb_strtoupper(trim($item['status'])) === 'FINALIZADO'
        );
    }

    /**
     * @param  list<string>  $itemNames
     */
    private static function finalizeMedications(int $coordinationId, array $itemNames): int
    {
        return self::finalizeRecords(
            TelemedicinePatientMedications::query()
                ->where('operation_coordination_service_id', $coordinationId)
                ->where('status', 'EN GESTION')
                ->get(['id', 'medicine']),
            'medicine',
            $itemNames
        );
    }

    /**
     * @param  list<string>  $itemNames
     */
    private static function finalizeLabs(int $coordinationId, array $itemNames): int
    {
        return self::finalizeRecords(
            TelemedicinePatientLab::query()
                ->where('operation_coordination_service_id', $coordinationId)
                ->where('status', 'EN GESTION')
                ->get(['id', 'laboratory']),
            'laboratory',
            $itemNames
        );
    }

    /**
     * @param  list<string>  $itemNames
     */
    private static function finalizeStudies(int $coordinationId, array $itemNames): int
    {
        return self::finalizeRecords(
            TelemedicinePatientStudy::query()
                ->where('operation_coordination_service_id', $coordinationId)
                ->where('status', 'EN GESTION')
                ->get(['id', 'study']),
            'study',
            $itemNames
        );
    }

    /**
     * @param  list<string>  $itemNames
     */
    private static function finalizeSpecialties(int $coordinationId, array $itemNames): int
    {
        return self::finalizeRecords(
            TelemedicinePatientSpecialty::query()
                ->where('operation_coordination_service_id', $coordinationId)
                ->where('status', 'EN GESTION')
                ->get(['id', 'specialty']),
            'specialty',
            $itemNames
        );
    }

    /**
     * @param  iterable<int, TelemedicinePatientMedications|TelemedicinePatientLab|TelemedicinePatientStudy|TelemedicinePatientSpecialty>  $records
     * @param  list<string>  $itemNames
     */
    private static function finalizeRecords(iterable $records, string $nameAttribute, array $itemNames): int
    {
        return self::updateMatchedRecords($records, $nameAttribute, $itemNames, 'FINALIZADO');
    }

    /**
     * @param  list<string>  $itemNames
     */
    /**
     * Registros clínicos EN GESTION del servicio médico que corresponden a los ítems de la
     * orden: los que cancelar, liberar o finalizar la orden cambiaría. La eliminación de
     * proveedores los guarda antes de cancelar para poder devolverlos al restaurar.
     *
     * @return list<TelemedicinePatientMedications|TelemedicinePatientLab|TelemedicinePatientStudy|TelemedicinePatientSpecialty>
     */
    public static function clinicalItemsForOrder(OperationServiceOrder $order): array
    {
        $order->loadMissing('operationServiceOrderItems');

        $coordinationId = (int) ($order->operation_coordination_service_id ?? 0);

        if ($coordinationId <= 0) {
            return [];
        }

        /** @var list<string> $itemNames */
        $itemNames = $order->operationServiceOrderItems
            ->pluck('item_name')
            ->map(fn (mixed $name): string => self::normalizeItemName(is_string($name) ? $name : null))
            ->filter(fn (string $name): bool => $name !== '')
            ->unique()
            ->values()
            ->all();

        if ($itemNames === []) {
            return [];
        }

        $definition = self::CLINICAL_ITEM_SOURCES[mb_strtoupper(trim((string) ($order->service_type ?? '')))] ?? null;

        if ($definition === null) {
            return [];
        }

        [$model, $nameAttribute] = $definition;

        return $model::query()
            ->where('operation_coordination_service_id', $coordinationId)
            ->where('status', 'EN GESTION')
            ->get(['id', $nameAttribute, 'status'])
            ->filter(function (mixed $record) use ($nameAttribute, $itemNames): bool {
                $name = self::normalizeItemName($record->{$nameAttribute} ?? null);

                return $name !== '' && in_array($name, $itemNames, true);
            })
            ->values()
            ->all();
    }

    private static function applyClinicalItemStatus(OperationServiceOrder $order, string $status): int
    {
        $updated = 0;

        foreach (self::clinicalItemsForOrder($order) as $record) {
            $record->update(['status' => $status]);
            $updated++;
        }

        return $updated;
    }

    /**
     * @param  iterable<int, TelemedicinePatientMedications|TelemedicinePatientLab|TelemedicinePatientStudy|TelemedicinePatientSpecialty>  $records
     * @param  list<string>  $itemNames
     */
    private static function updateMatchedRecords(iterable $records, string $nameAttribute, array $itemNames, string $status): int
    {
        $updated = 0;

        foreach ($records as $record) {
            $name = self::normalizeItemName($record->{$nameAttribute} ?? null);

            if ($name === '' || ! in_array($name, $itemNames, true)) {
                continue;
            }

            $record->update(['status' => $status]);
            $updated++;
        }

        return $updated;
    }

    private static function normalizeItemName(?string $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }
}
