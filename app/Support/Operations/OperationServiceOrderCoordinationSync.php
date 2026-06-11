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

        return match ($serviceType) {
            'MEDICAMENTOS' => self::cancelMedications($coordinationId, $itemNames),
            'LABORATORIOS' => self::cancelLabs($coordinationId, $itemNames),
            'IMAGENOLOGIA' => self::cancelStudies($coordinationId, $itemNames),
            'ESPECIALISTA' => self::cancelSpecialties($coordinationId, $itemNames),
            default => 0,
        };
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
    private static function cancelMedications(int $coordinationId, array $itemNames): int
    {
        return self::cancelRecords(
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
    private static function cancelLabs(int $coordinationId, array $itemNames): int
    {
        return self::cancelRecords(
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
    private static function cancelStudies(int $coordinationId, array $itemNames): int
    {
        return self::cancelRecords(
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
    private static function cancelSpecialties(int $coordinationId, array $itemNames): int
    {
        return self::cancelRecords(
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
    private static function cancelRecords(iterable $records, string $nameAttribute, array $itemNames): int
    {
        return self::updateMatchedRecords($records, $nameAttribute, $itemNames, 'CANCELADA');
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
