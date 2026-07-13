<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationCoordinationService;
use App\Models\Supplier;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Support\Filament\Operations\OperationsSupplierScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class OperationsDashboardMetrics
{
    /**
     * @return Builder<TelemedicinePatient>
     */
    public static function patientsQuery(): Builder
    {
        return OperationsSupplierScope::applyToQuery(TelemedicinePatient::query());
    }

    /**
     * @return Builder<TelemedicineCase>
     */
    public static function casesQuery(): Builder
    {
        return OperationsSupplierScope::applyToQuery(TelemedicineCase::query());
    }

    /**
     * @return Builder<OperationCoordinationService>
     */
    public static function coordinationServicesQuery(): Builder
    {
        return OperationsSupplierScope::coordinationServiceQuery();
    }

    public static function associatedPatientsCount(): int
    {
        return self::patientsQuery()
            ->where(function (Builder $query): void {
                $query->whereNotNull('afilliation_id')
                    ->orWhereNotNull('afilliation_corporate_id');
            })
            ->count();
    }

    public static function medicalDischargeCasesCount(): int
    {
        return self::casesQuery()
            ->where('status', 'ALTA MEDICA')
            ->count();
    }

    public static function followUpCasesCount(): int
    {
        return self::casesQuery()
            ->where('status', 'EN SEGUIMIENTO')
            ->count();
    }

    public static function associatedSuppliersCount(): int
    {
        return Supplier::query()
            ->whereIn('id', self::operationsPortalSupplierIds())
            ->count();
    }

    /**
     * @return Collection<int, int>
     */
    public static function operationsPortalSupplierIds(): Collection
    {
        return User::query()
            ->where('status', 'ACTIVO')
            ->whereNotNull('supplier_id')
            ->get(['supplier_id', 'departament', 'is_proveedor_amd'])
            ->filter(fn (User $user): bool => self::userHasOperationsPortalAccess($user))
            ->pluck('supplier_id')
            ->map(fn (mixed $supplierId): int => (int) $supplierId)
            ->unique()
            ->values();
    }

    public static function userHasOperationsPortalAccess(User $user): bool
    {
        $departaments = is_array($user->departament) ? $user->departament : [];

        return in_array('OPERACIONES', $departaments, true)
            || in_array('TELEMEDICINA', $departaments, true)
            || $user->isProveedorAmd();
    }

    /**
     * @return Collection<int, object{telemedicine_patient_id: int, total: int, full_name: ?string}>
     */
    public static function topPatientsByMedicalDischargeCases(int $limit = 20): Collection
    {
        $rows = self::casesQuery()
            ->where('status', 'ALTA MEDICA')
            ->whereNotNull('telemedicine_patient_id')
            ->select([
                'telemedicine_patient_id',
                DB::raw('COUNT(*) as total'),
            ])
            ->groupBy('telemedicine_patient_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $patientNames = TelemedicinePatient::query()
            ->whereIn('id', $rows->pluck('telemedicine_patient_id'))
            ->pluck('full_name', 'id');

        return $rows->map(function (object $row) use ($patientNames): object {
            $patientId = (int) $row->telemedicine_patient_id;

            return (object) [
                'telemedicine_patient_id' => $patientId,
                'total' => (int) $row->total,
                'full_name' => $patientNames->get($patientId),
            ];
        });
    }

    /**
     * @return Collection<int, TelemedicineCase>
     */
    public static function medicalDischargeCasesForPatient(int $patientId): Collection
    {
        return self::casesQuery()
            ->where('telemedicine_patient_id', $patientId)
            ->where('status', 'ALTA MEDICA')
            ->orderByDesc('updated_at')
            ->get(['id', 'code', 'created_at', 'updated_at']);
    }
}
