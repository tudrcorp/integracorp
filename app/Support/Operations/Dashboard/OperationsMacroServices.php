<?php

declare(strict_types=1);

namespace App\Support\Operations\Dashboard;

use App\Models\OperationServiceStatistic;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineServiceList;
use App\Support\Filament\Operations\OperationsSupplierScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Servicios principales (macro) del Dashboard de Operaciones: telemedicina, AMD,
 * traslados, seguimientos… Lo que el médico elige como «Tipo de Servicio» en la
 * consulta (`telemedicine_service_lists`).
 *
 * La tabla de estadísticas solo guarda los servicios derivados de una consulta
 * (medicamentos, laboratorios, estudios, especialistas) y las coordinaciones
 * sueltas, así que el macro se cuenta desde dos fuentes:
 *
 * 1. Cada consulta, por su Tipo de Servicio.
 * 2. Las filas de estadísticas cuyo tipo es un servicio macro (un traslado
 *    ordenado durante una consulta AMD, un AMD registrado como RETAIL…),
 *    **salvo** que el mismo caso ya tenga una consulta de ese mismo servicio:
 *    ahí es el mismo servicio y contarlo otra vez lo duplicaría.
 *
 * El catálogo repite nombres por nivel (AMD, Traslado, Seguimiento…) y a veces
 * cambia una tilde («CENTRO MÉDICO» / «CENTRO MEDICO»): se agrupa por el nombre
 * normalizado, no por id.
 */
final class OperationsMacroServices
{
    /**
     * Tipos de las estadísticas que nombran un servicio macro con otro texto.
     *
     * @var array<string, string> tipo normalizado => nombre normalizado del catálogo
     */
    public const ALIASES = [
        'TELEMEDICINA' => 'ATENCION MEDICA TELEFONICA (TELEMEDICINA)',
    ];

    /** @var array{labels: array<string, string>, keysById: array<int, string>}|null */
    private static ?array $catalog = null;

    public static function flush(): void
    {
        self::$catalog = null;
    }

    /**
     * Clave de agrupación: mayúsculas, sin tildes y sin espacios repetidos.
     */
    public static function normalize(?string $name): string
    {
        $ascii = Str::ascii(trim((string) $name));

        return mb_strtoupper((string) preg_replace('/\s+/', ' ', $ascii));
    }

    /**
     * Clave del servicio macro que nombra un tipo de las estadísticas, o null si
     * es un servicio derivado.
     */
    public static function keyForStatisticType(?string $serviceType): ?string
    {
        $key = self::normalize($serviceType);
        $key = self::ALIASES[$key] ?? $key;

        return array_key_exists($key, self::catalog()['labels']) ? $key : null;
    }

    public static function labelForKey(string $key): string
    {
        return self::catalog()['labels'][$key] ?? $key;
    }

    public static function keyForLabel(string $label): ?string
    {
        $key = self::normalize($label);

        return array_key_exists($key, self::catalog()['labels']) ? $key : null;
    }

    /**
     * Servicios macro del período, de mayor a menor.
     *
     * @param  array<string, int>  $statisticTypeCounts  conteo de estadísticas por tipo (ya en el período)
     * @return array<string, int> etiqueta => cantidad
     */
    public static function counts(CarbonImmutable $from, CarbonImmutable $to, array $statisticTypeCounts): array
    {
        $byKey = [];

        $consultations = self::consultationsInPeriod($from, $to)
            ->selectRaw('telemedicine_service_list_id, COUNT(*) as total')
            ->groupBy('telemedicine_service_list_id')
            ->toBase()
            ->get();

        foreach ($consultations as $row) {
            $key = self::catalog()['keysById'][(int) $row->telemedicine_service_list_id] ?? null;

            if ($key !== null) {
                $byKey[$key] = ($byKey[$key] ?? 0) + (int) $row->total;
            }
        }

        $macroTypes = array_values(array_filter(
            array_keys($statisticTypeCounts),
            static fn (string $type): bool => self::keyForStatisticType($type) !== null,
        ));

        foreach (self::uniqueStatisticRows($from, $to, $macroTypes) as $row) {
            $key = (string) self::keyForStatisticType($row->service_type);
            $byKey[$key] = ($byKey[$key] ?? 0) + 1;
        }

        $counts = [];

        foreach ($byKey as $key => $total) {
            $counts[self::labelForKey($key)] = $total;
        }

        arsort($counts);

        return $counts;
    }

    /**
     * Detalle de un servicio macro: sus consultas y las filas de estadísticas
     * que no duplican una consulta, de la más reciente a la más antigua.
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public static function detail(CarbonImmutable $from, CarbonImmutable $to, string $label, int $limit = 200): array
    {
        $key = self::keyForLabel($label);

        if ($key === null) {
            return ['rows' => [], 'total' => 0];
        }

        $label = self::labelForKey($key);
        $serviceListIds = array_keys(array_filter(self::catalog()['keysById'], static fn (string $value): bool => $value === $key));

        $consultationQuery = self::consultationsInPeriod($from, $to)->whereIn('telemedicine_service_list_id', $serviceListIds);
        $consultationTotal = (clone $consultationQuery)->count();

        $consultationRows = $consultationQuery
            ->with([
                'telemedicineCase:id,code',
                'telemedicinePatient:id,full_name,nro_identificacion,state_id,city_id',
                'telemedicinePatient.state:id,definition',
                'telemedicinePatient.city:id,definition',
            ])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'telemedicine_case_id', 'telemedicine_patient_id', 'telemedicine_service_list_id', 'status', 'created_at'])
            ->map(static fn (TelemedicineConsultationPatient $consultation): array => [
                'id' => 'consulta-'.$consultation->id,
                'case_id' => $consultation->telemedicine_case_id !== null ? (int) $consultation->telemedicine_case_id : null,
                'case_code' => $consultation->telemedicineCase?->code,
                'sort' => (string) $consultation->created_at?->format('Y-m-d H:i:s'),
                'date' => $consultation->created_at?->format('d/m/Y'),
                'time' => $consultation->created_at?->format('H:i'),
                'patient' => trim((string) ($consultation->telemedicinePatient?->full_name ?? '')) ?: null,
                'service' => $label,
                'type' => 'Servicio principal',
                'status' => $consultation->status,
                'line' => null,
                'place' => trim(implode(', ', array_filter([
                    $consultation->telemedicinePatient?->city?->definition,
                    $consultation->telemedicinePatient?->state?->definition,
                ])), ', '),
                'provider' => null,
            ]);

        $statisticTypes = array_values(array_filter(
            self::statisticTypesInPeriod($from, $to),
            static fn (string $type): bool => self::keyForStatisticType($type) === $key,
        ));
        $statisticIds = array_map(static fn (object $row): int => (int) $row->id, self::uniqueStatisticRows($from, $to, $statisticTypes));

        $statisticRows = $statisticIds === [] ? collect() : OperationServiceStatistic::query()
            ->whereKey($statisticIds)
            ->orderByRaw(OperationsDashboardAnalytics::DATE_SQL.' DESC')
            ->limit($limit)
            ->get([
                'id', 'telemedicine_case_id', 'case_code', 'service_on', 'started_on', 'started_at_time', 'patient_name',
                'service', 'specific_service', 'service_type', 'service_status', 'state', 'city', 'service_provider',
            ])
            ->map(static fn (OperationServiceStatistic $row): array => [
                'id' => 'estadistica-'.$row->id,
                'case_id' => $row->telemedicine_case_id !== null ? (int) $row->telemedicine_case_id : null,
                'case_code' => $row->case_code,
                'sort' => trim(substr((string) ($row->service_on ?? $row->started_on), 0, 10).' '.(string) $row->started_at_time),
                'date' => filled($row->service_on ?? $row->started_on) ? CarbonImmutable::parse($row->service_on ?? $row->started_on)->format('d/m/Y') : null,
                'time' => filled($row->started_at_time) ? substr((string) $row->started_at_time, 0, 5) : null,
                'patient' => $row->patient_name,
                'service' => filled($row->specific_service) ? $row->specific_service : $row->service,
                'type' => $row->service_type,
                'status' => $row->service_status,
                'line' => null,
                'place' => trim(implode(', ', array_filter([$row->city, $row->state])), ', '),
                'provider' => $row->service_provider,
            ]);

        $rows = $consultationRows
            ->concat($statisticRows)
            ->sortByDesc('sort')
            ->take($limit)
            ->map(static function (array $row): array {
                unset($row['sort']);

                return $row;
            })
            ->values()
            ->all();

        return ['rows' => $rows, 'total' => $consultationTotal + count($statisticIds)];
    }

    /**
     * Consultas del período con Tipo de Servicio, en el alcance del usuario: un
     * proveedor o ATENMEDI solo ve las de los casos de sus coordinaciones (la
     * misma regla que las estadísticas). Las de casos eliminados las oculta el
     * scope global del modelo.
     *
     * @return Builder<TelemedicineConsultationPatient>
     */
    private static function consultationsInPeriod(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $query = TelemedicineConsultationPatient::query()
            ->whereNotNull('telemedicine_service_list_id')
            ->whereBetween('created_at', [$from->startOfDay()->toDateTimeString(), $to->endOfDay()->toDateTimeString()]);

        if (self::isScopedUser()) {
            $query->whereIn(
                'telemedicine_case_id',
                OperationsSupplierScope::coordinationServiceQuery()->whereNotNull('telemedicine_case_id')->select('telemedicine_case_id')
            );
        }

        return $query;
    }

    private static function isScopedUser(): bool
    {
        $user = Auth::user();

        return OperationsSupplierScope::currentSupplierId() !== null
            || in_array('ATENMEDI', is_array($user?->departament) ? $user->departament : [], true);
    }

    /**
     * Filas de estadísticas de tipo macro que no repiten una consulta del mismo
     * servicio en su caso.
     *
     * @param  list<string>  $types  tipos tal como los agrupa el dashboard (mayúsculas)
     * @return list<object{id: int, telemedicine_case_id: int|null, service_type: string}>
     */
    private static function uniqueStatisticRows(CarbonImmutable $from, CarbonImmutable $to, array $types): array
    {
        if ($types === []) {
            return [];
        }

        $rows = OperationsDashboardAnalytics::inPeriod($from, $to)
            ->whereRaw('UPPER(TRIM(service_type)) IN ('.implode(', ', array_fill(0, count($types), '?')).')', $types)
            ->toBase()
            ->get(['id', 'telemedicine_case_id', 'service_type'])
            ->all();

        $caseIds = array_values(array_unique(array_filter(array_map(
            static fn (object $row): int => (int) ($row->telemedicine_case_id ?? 0),
            $rows,
        ))));

        /** @var array<int, array<string, true>> $consultedByCase caso => servicios macro con consulta */
        $consultedByCase = [];

        if ($caseIds !== []) {
            TelemedicineConsultationPatient::query()
                ->whereIn('telemedicine_case_id', $caseIds)
                ->whereNotNull('telemedicine_service_list_id')
                ->toBase()
                ->distinct()
                ->get(['telemedicine_case_id', 'telemedicine_service_list_id'])
                ->each(static function (object $consultation) use (&$consultedByCase): void {
                    $key = self::catalog()['keysById'][(int) $consultation->telemedicine_service_list_id] ?? null;

                    if ($key !== null) {
                        $consultedByCase[(int) $consultation->telemedicine_case_id][$key] = true;
                    }
                });
        }

        return array_values(array_filter($rows, static function (object $row) use ($consultedByCase): bool {
            $key = self::keyForStatisticType($row->service_type);

            return $key !== null && ! isset($consultedByCase[(int) ($row->telemedicine_case_id ?? 0)][$key]);
        }));
    }

    /**
     * @return list<string>
     */
    private static function statisticTypesInPeriod(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return OperationsDashboardAnalytics::inPeriod($from, $to)
            ->whereNotNull('service_type')
            ->toBase()
            ->distinct()
            ->pluck('service_type')
            ->map(static fn (mixed $type): string => mb_strtoupper(trim((string) $type)))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Catálogo de servicios macro agrupado por nombre normalizado. La etiqueta es
     * la del registro de menor nivel e id (el principal de cada nombre).
     *
     * @return array{labels: array<string, string>, keysById: array<int, string>}
     */
    private static function catalog(): array
    {
        if (self::$catalog !== null) {
            return self::$catalog;
        }

        $labels = [];
        $keysById = [];

        TelemedicineServiceList::query()
            ->orderBy('level')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->each(static function (TelemedicineServiceList $service) use (&$labels, &$keysById): void {
                $key = self::normalize($service->name);

                if ($key === '') {
                    return;
                }

                $labels[$key] ??= mb_strtoupper(trim((string) $service->name));
                $keysById[(int) $service->id] = $key;
            });

        return self::$catalog = ['labels' => $labels, 'keysById' => $keysById];
    }
}
