<?php

declare(strict_types=1);

namespace App\Support\Filament\Operations;

use App\Models\OperationCoordinationService;
use App\Models\OperationServiceOrder;
use App\Models\TelemedicineDoctor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

final class OperationsSupplierScope
{
    public static function currentSupplierId(): ?int
    {
        $supplierId = Auth::user()?->supplier_id;

        return filled($supplierId) ? (int) $supplierId : null;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function applyToQuery(Builder $query, string $column = 'supplier_id'): Builder
    {
        $supplierId = self::currentSupplierId();

        if ($supplierId !== null) {
            $query->where($column, $supplierId);
        }

        return $query;
    }

    public static function resolveFromPatient(?object $patient): ?int
    {
        $patientSupplierId = data_get($patient, 'supplier_id');

        if (filled($patientSupplierId)) {
            return (int) $patientSupplierId;
        }

        return self::currentSupplierId();
    }

    public static function resolveFromPatientAndDoctor(?int $patientSupplierId = null, ?int $doctorSupplierId = null): ?int
    {
        $fromUser = self::currentSupplierId();

        if ($fromUser !== null) {
            return $fromUser;
        }

        if (filled($patientSupplierId)) {
            return (int) $patientSupplierId;
        }

        if (filled($doctorSupplierId)) {
            return (int) $doctorSupplierId;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|object|null  $doctor
     */
    public static function resolveFromDoctor(array|object|null $doctor): ?int
    {
        $doctorSupplierId = data_get($doctor, 'supplier_id');

        if (filled($doctorSupplierId)) {
            return (int) $doctorSupplierId;
        }

        return self::currentSupplierId();
    }

    /**
     * @param  array<string, mixed>|object|null  $doctor
     */
    public static function managedByFromDoctor(array|object|null $doctor): ?string
    {
        $managedBy = data_get($doctor, 'managed_by');

        return filled($managedBy) ? (string) $managedBy : null;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function applyCoordinationListScope(Builder $query): Builder
    {
        self::applyToQuery($query);

        if (in_array('ATENMEDI', Auth::user()?->departament ?? [], true)) {
            $query->where('managed_by', 'ATENMEDI');
        }

        return $query;
    }

    /**
     * @return Builder<OperationCoordinationService>
     */
    public static function coordinationServiceQuery(): Builder
    {
        return self::applyCoordinationListScope(OperationCoordinationService::query());
    }

    /**
     * @param  array<string, mixed>|object|null  $coordination
     */
    public static function resolveTelemedicineSupplierIdFromCoordination(array|object|null $coordination): ?int
    {
        $coordinationSupplierId = data_get($coordination, 'supplier_id');

        if (filled($coordinationSupplierId)) {
            return (int) $coordinationSupplierId;
        }

        $doctorId = data_get($coordination, 'telemedicine_doctor_id');

        if (filled($doctorId)) {
            $doctorSupplierId = TelemedicineDoctor::query()->whereKey($doctorId)->value('supplier_id');

            if (filled($doctorSupplierId)) {
                return (int) $doctorSupplierId;
            }
        }

        return self::currentSupplierId();
    }

    /**
     * @param  array<string, mixed>|object|null  $coordination
     */
    public static function managedByFromCoordination(array|object|null $coordination): ?string
    {
        $managedBy = data_get($coordination, 'managed_by');

        if (filled($managedBy)) {
            return (string) $managedBy;
        }

        $doctorId = data_get($coordination, 'telemedicine_doctor_id');

        if (filled($doctorId)) {
            $doctorManagedBy = TelemedicineDoctor::query()->whereKey($doctorId)->value('managed_by');

            if (filled($doctorManagedBy)) {
                return (string) $doctorManagedBy;
            }
        }

        return null;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function applyServiceOrderListScope(Builder $query): Builder
    {
        self::applyToQuery($query, 'telemedicine_supplier_id');

        if (in_array('ATENMEDI', Auth::user()?->departament ?? [], true)) {
            $query->where('managed_by', 'ATENMEDI');
        }

        return $query;
    }

    /**
     * @return Builder<OperationServiceOrder>
     */
    public static function serviceOrderQuery(): Builder
    {
        return self::applyServiceOrderListScope(OperationServiceOrder::query());
    }
}
