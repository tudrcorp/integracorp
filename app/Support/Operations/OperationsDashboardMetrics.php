<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationCoordinationService;
use App\Models\OperationServiceStatistic;
use App\Models\Supplier;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Support\Filament\Operations\OperationsSupplierScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

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
     * @return Builder<OperationServiceStatistic>
     */
    public static function statisticsQuery(): Builder
    {
        $query = OperationServiceStatistic::query();

        $user = Auth::user();
        $supplierId = OperationsSupplierScope::currentSupplierId();
        $isAtenmedi = in_array('ATENMEDI', is_array($user?->departament) ? $user->departament : [], true);

        if ($supplierId !== null || $isAtenmedi) {
            $query->whereIn(
                'operation_coordination_service_id',
                OperationsSupplierScope::coordinationServiceQuery()->select('id')
            );
        }

        return $query;
    }

    /**
     * @return array<string, int>
     */
    public static function countsByServiceStatus(): array
    {
        return self::countsGroupedBy('service_status', 'Sin estatus', [
            'PENDIENTE',
            'EN GESTION',
            'FINALIZADO',
            'REASIGNADO A TDG',
        ]);
    }

    /**
     * @return array<string, int>
     */
    public static function countsByBusinessLine(): array
    {
        return self::countsGroupedBy('business_line', 'Sin línea', [
            'CORPORATIVOS',
            'INDIVIDUALES',
        ]);
    }

    /**
     * @return array<string, int>
     */
    public static function countsByServiceType(): array
    {
        return self::countsGroupedBy('service_type', 'Sin tipo', [
            'LABORATORIOS',
            'MEDICAMENTOS',
            'IMAGENOLOGIA',
            'ESPECIALISTA',
            'TRASLADO EN AMBULANCIA',
            'INGRESO A CLINICA',
        ]);
    }

    /**
     * @param  list<string>  $preferredOrder
     * @return array<string, int>
     */
    public static function countsGroupedBy(string $column, string $emptyLabel, array $preferredOrder = []): array
    {
        $allowed = ['service_status', 'business_line', 'service_type'];

        if (! in_array($column, $allowed, true)) {
            return [];
        }

        $rows = self::statisticsQuery()
            ->selectRaw($column.' as grouping_key, COUNT(*) as total')
            ->groupBy($column)
            ->pluck('total', 'grouping_key');

        $counts = [];

        foreach ($rows as $key => $total) {
            $label = trim((string) $key);
            if ($label === '') {
                $label = $emptyLabel;
            }

            $counts[$label] = ($counts[$label] ?? 0) + (int) $total;
        }

        $ordered = [];

        foreach ($preferredOrder as $label) {
            if (array_key_exists($label, $counts)) {
                $ordered[$label] = $counts[$label];
                unset($counts[$label]);
            }
        }

        arsort($counts);

        return $ordered + $counts;
    }

    /**
     * @return Collection<int, object{patient_key: string, total: int, full_name: ?string}>
     */
    public static function topPatientsByMedicalDischargeCases(int $limit = 20): Collection
    {
        $patientKeySql = self::patientKeySql();

        return self::statisticsQuery()
            ->where('case_status', 'ALTA MEDICA')
            ->whereNotNull('telemedicine_case_id')
            ->selectRaw($patientKeySql.' as patient_key')
            ->selectRaw('MAX(patient_name) as full_name')
            ->selectRaw('COUNT(DISTINCT telemedicine_case_id) as total')
            ->groupByRaw($patientKeySql)
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(function (object $row): object {
                return (object) [
                    'patient_key' => (string) $row->patient_key,
                    'full_name' => filled($row->full_name) ? (string) $row->full_name : null,
                    'total' => (int) $row->total,
                ];
            });
    }

    /**
     * @return Collection<int, object{
     *     id: int,
     *     code: ?string,
     *     started_on: mixed,
     *     service_on: mixed,
     *     patient_name: ?string,
     *     patient_document: ?string,
     *     patient_age: mixed,
     *     patient_relationship: ?string,
     *     contractor: ?string,
     *     plan_holder_name: ?string,
     *     business_line: ?string,
     *     consultation_reason: ?string,
     *     initial_diagnosis: ?string,
     *     final_diagnosis: ?string,
     *     medical_provider: ?string,
     *     management_provider: ?string,
     *     city: ?string,
     *     state: ?string,
     *     services: ?string,
     *     service_types: ?string
     * }>
     */
    public static function medicalDischargeCasesForPatient(string $patientKey): Collection
    {
        $patientKey = trim($patientKey);

        if ($patientKey === '') {
            return collect();
        }

        return self::constrainByPatientKey(
            self::statisticsQuery()->where('case_status', 'ALTA MEDICA'),
            $patientKey,
        )
            ->whereNotNull('telemedicine_case_id')
            ->selectRaw('telemedicine_case_id as id')
            ->selectRaw('MAX(case_code) as code')
            ->selectRaw('MAX(started_on) as started_on')
            ->selectRaw('MAX(service_on) as service_on')
            ->selectRaw('MAX(patient_name) as patient_name')
            ->selectRaw('MAX(patient_document) as patient_document')
            ->selectRaw('MAX(patient_age) as patient_age')
            ->selectRaw('MAX(patient_relationship) as patient_relationship')
            ->selectRaw('MAX(contractor) as contractor')
            ->selectRaw('MAX(plan_holder_name) as plan_holder_name')
            ->selectRaw('MAX(business_line) as business_line')
            ->selectRaw('MAX(consultation_reason) as consultation_reason')
            ->selectRaw('MAX(initial_diagnosis) as initial_diagnosis')
            ->selectRaw('MAX(final_diagnosis) as final_diagnosis')
            ->selectRaw('MAX(medical_provider) as medical_provider')
            ->selectRaw('MAX(management_provider) as management_provider')
            ->selectRaw('MAX(city) as city')
            ->selectRaw('MAX(state) as state')
            ->selectRaw("GROUP_CONCAT(DISTINCT NULLIF(TRIM(specific_service), '') ORDER BY specific_service SEPARATOR '||') as services")
            ->selectRaw("GROUP_CONCAT(DISTINCT NULLIF(TRIM(service_type), '') ORDER BY service_type SEPARATOR '||') as service_types")
            ->groupBy('telemedicine_case_id')
            ->orderByDesc('started_on')
            ->get();
    }

    /**
     * @return list<string>
     */
    public static function medicalDischargeCaseHoverLines(object $case): array
    {
        $lines = [];

        $patientBits = [];
        $patientName = self::plainText($case->patient_name ?? null);
        if ($patientName !== null) {
            $patientBits[] = $patientName;
        }

        $age = (int) ($case->patient_age ?? 0);
        if ($age > 0) {
            $patientBits[] = $age.' años';
        }

        $relationship = self::plainText($case->patient_relationship ?? null);
        if ($relationship !== null) {
            $patientBits[] = $relationship;
        }

        if ($patientBits !== []) {
            $lines[] = 'Paciente: '.implode(' · ', $patientBits);
        }

        $document = self::plainText($case->patient_document ?? null);
        if ($document !== null) {
            $lines[] = 'Documento: '.$document;
        }

        $contractor = self::plainText($case->contractor ?? null);
        if ($contractor !== null && strcasecmp($contractor, (string) $patientName) !== 0) {
            $lines[] = 'Contratante: '.self::clip($contractor);
        }

        $planHolder = self::plainText($case->plan_holder_name ?? null);
        if ($planHolder !== null && strcasecmp($planHolder, (string) $patientName) !== 0) {
            $lines[] = 'Titular: '.self::clip($planHolder);
        }

        $businessLine = self::plainText($case->business_line ?? null);
        if ($businessLine !== null) {
            $lines[] = 'Línea: '.$businessLine;
        }

        $reason = self::plainText($case->consultation_reason ?? null);
        if ($reason !== null) {
            $lines[] = 'Motivo: '.self::clip($reason);
        }

        $initialDiagnosis = self::plainText($case->initial_diagnosis ?? null);
        $finalDiagnosis = self::plainText($case->final_diagnosis ?? null);

        if ($initialDiagnosis !== null && $finalDiagnosis !== null && strcasecmp($initialDiagnosis, $finalDiagnosis) === 0) {
            $lines[] = 'Diagnóstico: '.self::clip($finalDiagnosis);
        } else {
            if ($initialDiagnosis !== null) {
                $lines[] = 'Dx inicial: '.self::clip($initialDiagnosis);
            }
            if ($finalDiagnosis !== null) {
                $lines[] = 'Dx final: '.self::clip($finalDiagnosis);
            }
        }

        $types = self::splitConcatenated($case->service_types ?? null);
        if ($types !== []) {
            $lines[] = 'Tipos: '.self::clip(implode(' · ', $types), 90);
        }

        $services = self::splitConcatenated($case->services ?? null);
        if ($services !== []) {
            $shown = array_slice($services, 0, 4);
            $extra = count($services) - count($shown);
            $text = implode(' · ', $shown);
            if ($extra > 0) {
                $text .= ' · +'.$extra;
            }
            $lines[] = 'Servicios: '.self::clip($text, 90);
        }

        $doctor = self::plainText($case->medical_provider ?? null);
        if ($doctor !== null) {
            $lines[] = 'Médico: '.self::clip($doctor);
        }

        $manager = self::plainText($case->management_provider ?? null);
        if ($manager !== null && strcasecmp($manager, (string) $doctor) !== 0) {
            $lines[] = 'Gestión: '.self::clip($manager, 80);
        }

        $city = self::plainText($case->city ?? null);
        $state = self::plainText($case->state ?? null);
        $location = match (true) {
            $city !== null && $state !== null && strcasecmp($city, $state) !== 0 => $city.', '.$state,
            $city !== null => $city,
            $state !== null => $state,
            default => null,
        };
        if ($location !== null) {
            $lines[] = 'Ubicación: '.$location;
        }

        return $lines;
    }

    /**
     * @return list<int>
     */
    public static function finishedServicesMonthlyCounts(int $year): array
    {
        $dateExpression = 'COALESCE(service_on, started_on)';

        $rows = self::statisticsQuery()
            ->where('service_status', 'FINALIZADO')
            ->whereRaw($dateExpression.' IS NOT NULL')
            ->whereRaw('YEAR('.$dateExpression.') = ?', [$year])
            ->selectRaw('MONTH('.$dateExpression.') as month_number')
            ->selectRaw('COUNT(*) as total')
            ->groupByRaw('MONTH('.$dateExpression.')')
            ->pluck('total', 'month_number');

        $counts = [];

        for ($month = 1; $month <= 12; $month++) {
            $counts[] = (int) ($rows[$month] ?? $rows[(string) $month] ?? 0);
        }

        return $counts;
    }

    private static function patientKeySql(): string
    {
        return "CASE
            WHEN TRIM(COALESCE(patient_document, '')) <> '' THEN TRIM(patient_document)
            WHEN TRIM(COALESCE(patient_name, '')) <> '' THEN CONCAT('nombre:', TRIM(patient_name))
            ELSE CONCAT('caso:', COALESCE(telemedicine_case_id, 0))
        END";
    }

    /**
     * @param  Builder<OperationServiceStatistic>  $query
     * @return Builder<OperationServiceStatistic>
     */
    private static function constrainByPatientKey(Builder $query, string $patientKey): Builder
    {
        if (str_starts_with($patientKey, 'nombre:')) {
            $name = substr($patientKey, strlen('nombre:'));

            return $query
                ->where('patient_name', $name)
                ->where(function (Builder $inner): void {
                    $inner->whereNull('patient_document')
                        ->orWhereRaw("TRIM(COALESCE(patient_document, '')) = ''");
                });
        }

        if (str_starts_with($patientKey, 'caso:')) {
            return $query->where('telemedicine_case_id', (int) substr($patientKey, strlen('caso:')));
        }

        return $query->where('patient_document', $patientKey);
    }

    private static function plainText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim(preg_replace('/\s+/', ' ', (string) $value) ?? (string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * @return list<string>
     */
    private static function splitConcatenated(mixed $value): array
    {
        $text = self::plainText($value);

        if ($text === null) {
            return [];
        }

        $unique = [];

        foreach (preg_split('/\s*\|\|\s*/', $text) ?: [] as $part) {
            $item = self::plainText($part);

            if ($item === null || in_array($item, $unique, true)) {
                continue;
            }

            $unique[] = $item;
        }

        return $unique;
    }

    private static function clip(string $text, int $max = 72): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max - 1).'…';
    }
}
