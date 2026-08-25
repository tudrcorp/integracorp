<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\DoctorNurse;
use App\Models\OperationServiceOrder;
use App\Models\Supplier;

final class OperationServiceOrderProviderSummary
{
    public static function address(OperationServiceOrder $order): ?string
    {
        self::loadProviderRelations($order);

        if (filled($order->approvedOperationQuote?->supplier_address)) {
            return trim((string) $order->approvedOperationQuote->supplier_address);
        }

        if ($order->supplier instanceof Supplier) {
            return self::addressFromSupplier($order->supplier);
        }

        if ($order->doctorNurse instanceof DoctorNurse) {
            return self::addressFromDoctorNurse($order->doctorNurse);
        }

        return null;
    }

    public static function addressOrDash(OperationServiceOrder $order): string
    {
        return self::address($order) ?? '—';
    }

    /**
     * @param  list<string>  $relations
     */
    private static function loadMissingIfNeeded(OperationServiceOrder|Supplier $model, array $relations): void
    {
        $missing = array_values(array_filter(
            $relations,
            static fn (string $relation): bool => ! $model->relationLoaded($relation)
        ));

        if ($missing === []) {
            return;
        }

        $model->loadMissing($missing);
    }

    private static function loadProviderRelations(OperationServiceOrder $order): void
    {
        self::loadMissingIfNeeded($order, [
            'approvedOperationQuote',
            'supplier',
            'doctorNurse',
        ]);

        if ($order->supplier instanceof Supplier) {
            self::loadMissingIfNeeded($order->supplier, ['state', 'city']);
        }
    }

    private static function addressFromSupplier(Supplier $supplier): ?string
    {
        if (filled($supplier->ubicacion_principal)) {
            return trim((string) $supplier->ubicacion_principal);
        }

        return self::composeLocation(
            $supplier->state?->definition,
            $supplier->city?->definition,
        );
    }

    private static function addressFromDoctorNurse(DoctorNurse $doctorNurse): ?string
    {
        if (filled($doctorNurse->ubicacion_principal)) {
            return trim((string) $doctorNurse->ubicacion_principal);
        }

        return self::composeLocation(
            $doctorNurse->getAttributes()['state'] ?? null,
            $doctorNurse->getAttributes()['city'] ?? null,
        );
    }

    private static function composeLocation(mixed $state, mixed $city): ?string
    {
        $parts = array_values(array_filter([
            filled($state) ? trim((string) $state) : null,
            filled($city) ? trim((string) $city) : null,
        ]));

        return $parts === [] ? null : implode(' — ', $parts);
    }
}
