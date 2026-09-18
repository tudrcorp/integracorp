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
        'Estudios/Imágenes',
        'Especialistas',
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
        if (self::supplierId() === null) {
            return [];
        }

        $counts = [];

        foreach (self::SERVICE_TYPE_LABELS as $label) {
            $counts[$label] = match ($label) {
                'Medicamentos' => self::medicationCaseCount(),
                'Laboratorios' => self::consultationSelectionCaseCount('labs'),
                'Estudios/Imágenes' => self::consultationSelectionCaseCount('studies'),
                'Especialistas' => self::consultationSelectionCaseCount('consult_specialist'),
                default => self::macroServiceCaseCount($label),
            };
        }

        return $counts;
    }

    /**
     * @return Builder<TelemedicineCase>|null
     */
    private static function casesQuery(): ?Builder
    {
        $supplierId = self::supplierId();

        if ($supplierId === null) {
            return null;
        }

        return TelemedicineCase::query()->where('supplier_id', $supplierId);
    }

    private static function macroServiceCaseCount(string $macroKey): int
    {
        $patterns = self::MACRO_SERVICE_PATTERNS[$macroKey] ?? [];

        if ($patterns === []) {
            return 0;
        }

        $serviceListIds = TelemedicineServiceList::query()
            ->where(function (Builder $query) use ($patterns): void {
                foreach ($patterns as $pattern) {
                    $query->orWhere('name', 'like', $pattern);
                }
            })
            ->pluck('id');

        if ($serviceListIds->isEmpty()) {
            return 0;
        }

        return self::consultationsQuery()
            ->whereIn('telemedicine_service_list_id', $serviceListIds)
            ->distinct()
            ->count('telemedicine_case_id');
    }

    private static function medicationCaseCount(): int
    {
        return TelemedicinePatientMedications::query()
            ->whereIn('telemedicine_case_id', self::caseIdsQuery())
            ->distinct()
            ->count('telemedicine_case_id');
    }

    private static function consultationSelectionCaseCount(string $column): int
    {
        $allowed = ['labs', 'studies', 'consult_specialist'];

        if (! in_array($column, $allowed, true)) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count(self::EMPTY_SELECTION_VALUES), '?'));

        return self::consultationsQuery()
            ->whereRaw("TRIM(COALESCE({$column}, '')) NOT IN ({$placeholders})", self::EMPTY_SELECTION_VALUES)
            ->distinct()
            ->count('telemedicine_case_id');
    }

    /**
     * @return Builder<TelemedicineConsultationPatient>
     */
    private static function consultationsQuery(): Builder
    {
        return TelemedicineConsultationPatient::query()
            ->whereIn('telemedicine_case_id', self::caseIdsQuery());
    }

    /**
     * @return \Illuminate\Database\Query\Builder|Builder<TelemedicineCase>
     */
    private static function caseIdsQuery(): Builder
    {
        return TelemedicineCase::query()
            ->where('supplier_id', self::supplierId())
            ->select('id');
    }
}
