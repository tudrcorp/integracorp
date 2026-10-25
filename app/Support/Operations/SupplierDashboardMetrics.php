<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\Supplier;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicineServiceList;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Métricas del Dashboard del Proveedor: todo se acota al proveedor del usuario
 * autenticado y solo se resuelve si ese proveedor tiene habilitada la gestión
 * en Integracorp.
 */
final class SupplierDashboardMetrics
{
    /**
     * Patrones de nombre de "Tipo de servicio" (telemedicine_service_lists)
     * que alimentan las barras macro del gráfico.
     *
     * @var array<string, list<string>>
     */
    private const MACRO_SERVICE_PATTERNS = [
        'Telemedicina' => ['%TELEMEDICINA%'],
        'AMD' => ['%AMD%', '%DOMICIL%'],
    ];

    /**
     * Barras del gráfico, en orden de presentación.
     *
     * @var list<string>
     */
    public const SERVICE_TYPE_LABELS = [
        'Telemedicina',
        'AMD',
        'Medicamentos',
        'Laboratorios',
    ];

    /** Valores de texto que cuentan como "sin selección" en las tildes de la consulta. */
    private const EMPTY_SELECTION_VALUES = ['', '[]', '{}', 'null'];

    private static ?Supplier $cachedSupplier = null;

    private static bool $supplierResolved = false;

    public static function isAvailableFor(?User $user): bool
    {
        if ($user === null || $user->supplier_id === null) {
            return false;
        }

        return $user->supplierHasIntegracorpManagementEnabled();
    }

    /**
     * Proveedor del usuario autenticado, solo si su gestión Integracorp está activa.
     */
    public static function supplier(): ?Supplier
    {
        if (self::$supplierResolved) {
            return self::$cachedSupplier;
        }

        self::$supplierResolved = true;

        /** @var User|null $user */
        $user = Auth::user();

        if (! self::isAvailableFor($user)) {
            return self::$cachedSupplier = null;
        }

        return self::$cachedSupplier = Supplier::query()->find($user->supplier_id);
    }

    public static function supplierId(): ?int
    {
        return self::supplier()?->id;
    }

    public static function supplierName(): ?string
    {
        $name = self::supplier()?->name;

        if (! is_string($name)) {
            return null;
        }

        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);

        return $name === '' ? null : $name;
    }

    /**
     * Olvida el proveedor memorizado. Necesario en pruebas y jobs que cambian de usuario.
     */
    public static function flush(): void
    {
        self::$cachedSupplier = null;
        self::$supplierResolved = false;
    }

    public static function analystsCount(): int
    {
        $supplier = self::supplier();

        if ($supplier === null) {
            return 0;
        }

        return $supplier->integracorpAnalysts()
            ->where('status', 'ACTIVO')
            ->count();
    }

    public static function doctorsCount(): int
    {
        $supplierId = self::supplierId();

        if ($supplierId === null) {
            return 0;
        }

        return TelemedicineDoctor::query()
            ->where('supplier_id', $supplierId)
            ->count();
    }

    public static function followUpCasesCount(): int
    {
        return self::casesQuery()?->where('status', 'EN SEGUIMIENTO')->count() ?? 0;
    }

    public static function medicalDischargeCasesCount(): int
    {
        return self::casesQuery()?->where('status', 'ALTA MEDICA')->count() ?? 0;
    }

    public static function totalCasesCount(): int
    {
        return self::casesQuery()?->count() ?? 0;
    }

    /**
     * Casos distintos del proveedor por tipo de servicio. Un caso que consumió
     * varios servicios suma en cada barra que le corresponda.
     *
     * @return array<string, int>
     */
    public static function casesByServiceType(): array
    {
        return self::casesByServiceTypeBetween(null, null);
    }

    /**
     * Igual que {@see self::casesByServiceType()}, contando solo lo registrado en
     * el rango (consultas y medicamentos por su fecha de creación). Sin rango,
     * todo el historial.
     *
     * @return array<string, int>
     */
    public static function casesByServiceTypeBetween(?CarbonInterface $from, ?CarbonInterface $to): array
    {
        if (self::supplierId() === null) {
            return [];
        }

        $range = $from !== null && $to !== null
            ? [$from->copy()->startOfDay(), $to->copy()->endOfDay()]
            : null;

        $counts = [];

        foreach (self::SERVICE_TYPE_LABELS as $label) {
            $counts[$label] = match ($label) {
                'Medicamentos' => self::medicationCaseCount($range),
                'Laboratorios' => self::consultationSelectionCaseCount('labs', $range),
                default => self::macroServiceCaseCount($label, $range),
            };
        }

        return $counts;
    }

    /**
     * Ids de los servicios (lista de telemedicina) de una barra macro.
     *
     * @return list<int>
     */
    public static function macroServiceListIds(string $macroKey): array
    {
        $patterns = self::MACRO_SERVICE_PATTERNS[$macroKey] ?? [];

        if ($patterns === []) {
            return [];
        }

        return TelemedicineServiceList::query()
            ->where(function (Builder $query) use ($patterns): void {
                foreach ($patterns as $pattern) {
                    $query->orWhere('name', 'like', $pattern);
                }
            })
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * Ids de los casos del proveedor que consumieron un tipo de servicio en el rango.
     *
     * @param  array{0: CarbonInterface, 1: CarbonInterface}|null  $range
     * @return \Illuminate\Database\Query\Builder|Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function caseIdsForServiceType(string $label, ?array $range = null): Builder
    {
        return match ($label) {
            'Medicamentos' => TelemedicinePatientMedications::query()
                ->whereIn('telemedicine_case_id', self::caseIdsQuery())
                ->when($range !== null, fn (Builder $query) => $query->whereBetween('created_at', $range))
                ->select('telemedicine_case_id'),
            'Laboratorios' => self::selectionQuery('labs', $range)->select('telemedicine_case_id'),
            default => self::consultationsQuery()
                ->whereIn('telemedicine_service_list_id', self::macroServiceListIds($label) ?: [0])
                ->when($range !== null, fn (Builder $query) => $query->whereBetween('created_at', $range))
                ->select('telemedicine_case_id'),
        };
    }

    /**
     * @return Builder<TelemedicineCase>|null
     */
    public static function casesQuery(): ?Builder
    {
        $supplierId = self::supplierId();

        if ($supplierId === null) {
            return null;
        }

        return TelemedicineCase::query()->where('supplier_id', $supplierId);
    }

    /**
     * @param  array{0: CarbonInterface, 1: CarbonInterface}|null  $range
     */
    private static function macroServiceCaseCount(string $macroKey, ?array $range = null): int
    {
        $serviceListIds = self::macroServiceListIds($macroKey);

        if ($serviceListIds === []) {
            return 0;
        }

        return self::consultationsQuery()
            ->whereIn('telemedicine_service_list_id', $serviceListIds)
            ->when($range !== null, fn (Builder $query) => $query->whereBetween('created_at', $range))
            ->distinct()
            ->count('telemedicine_case_id');
    }

    /**
     * @param  array{0: CarbonInterface, 1: CarbonInterface}|null  $range
     */
    private static function medicationCaseCount(?array $range = null): int
    {
        return TelemedicinePatientMedications::query()
            ->whereIn('telemedicine_case_id', self::caseIdsQuery())
            ->when($range !== null, fn (Builder $query) => $query->whereBetween('created_at', $range))
            ->distinct()
            ->count('telemedicine_case_id');
    }

    /**
     * @param  array{0: CarbonInterface, 1: CarbonInterface}|null  $range
     */
    private static function consultationSelectionCaseCount(string $column, ?array $range = null): int
    {
        return self::selectionQuery($column, $range)
            ->distinct()
            ->count('telemedicine_case_id');
    }

    /**
     * Consultas del proveedor que indicaron algo en la tilde (`labs`, `studies`…).
     *
     * @param  array{0: CarbonInterface, 1: CarbonInterface}|null  $range
     * @return Builder<TelemedicineConsultationPatient>
     */
    private static function selectionQuery(string $column, ?array $range = null): Builder
    {
        $allowed = ['labs', 'studies', 'consult_specialist'];

        if (! in_array($column, $allowed, true)) {
            return self::consultationsQuery()->whereRaw('1 = 0');
        }

        $placeholders = implode(', ', array_fill(0, count(self::EMPTY_SELECTION_VALUES), '?'));

        return self::consultationsQuery()
            ->whereRaw("TRIM(COALESCE({$column}, '')) NOT IN ({$placeholders})", self::EMPTY_SELECTION_VALUES)
            ->when($range !== null, fn (Builder $query) => $query->whereBetween('created_at', $range));
    }

    /**
     * @return Builder<TelemedicineConsultationPatient>
     */
    public static function consultationsQuery(): Builder
    {
        return TelemedicineConsultationPatient::query()
            ->whereIn('telemedicine_case_id', self::caseIdsQuery());
    }

    /**
     * @return \Illuminate\Database\Query\Builder|Builder<TelemedicineCase>
     */
    public static function caseIdsQuery(): Builder
    {
        $supplierId = self::supplierId();

        /** Sin proveedor no hay casos: `where('supplier_id', null)` traería los de TDG (IS NULL). */
        if ($supplierId === null) {
            return TelemedicineCase::query()->whereRaw('1 = 0')->select('id');
        }

        return TelemedicineCase::query()
            ->where('supplier_id', $supplierId)
            ->select('id');
    }
}
