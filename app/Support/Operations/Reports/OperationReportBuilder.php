<?php

declare(strict_types=1);

namespace App\Support\Operations\Reports;

use App\Enums\OperationReportType;
use App\Models\OperationServiceStatistic;
use App\Support\Operations\OperationsDashboardMetrics;
use Generator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Arma cada reporte del generador de Operaciones sobre `operation_service_statistics`.
 *
 * Parte siempre de {@see OperationsDashboardMetrics::statisticsQuery()}, que ya
 * acota a proveedores y a ATENMEDI a sus propios servicios: el reporte nunca
 * muestra más de lo que el usuario ve en el dashboard.
 */
final class OperationReportBuilder
{
    /**
     * Lote de lectura del detalle y la tabla completa.
     */
    private const CHUNK_SIZE = 1000;

    /**
     * Columnas del volcado «Tabla completa», en el orden de la tabla.
     *
     * @var list<string>
     */
    public const FULL_TABLE_COLUMNS = [
        'id',
        'telemedicine_case_id',
        'telemedicine_consultation_patient_id',
        'operation_coordination_service_id',
        'operation_service_order_id',
        'source_type',
        'source_id',
        'started_on',
        'started_at_time',
        'service_on',
        'business_line',
        'case_code',
        'case_status',
        'service_status',
        'case_created_by',
        'case_last_touched_by',
        'plan_holder_name',
        'plan_holder_document',
        'patient_name',
        'patient_document',
        'patient_birth_date',
        'patient_relationship',
        'patient_age',
        'contractor',
        'agency_name',
        'agent_name',
        'region',
        'state',
        'city',
        'address',
        'patient_phone',
        'patient_email',
        'consultation_reason',
        'initial_diagnosis',
        'final_diagnosis',
        'service',
        'specific_service',
        'service_type',
        'coverage',
        'management_provider',
        'service_provider',
        'medical_provider',
        'farmadoc_derived',
        'farmadoc_detail',
        'negotiation_type',
        'negotiation_status',
        'net_price',
        'tdec_profit_percent',
        'quoted_amount',
        'discount_negotiation',
        'discount_percent',
        'discount_amount',
        'quote_number',
        'approval_number',
        'service_order_number',
        'invoice_number',
        'invoiced_amount',
        'invoice_issued_on',
        'incidence',
        'case_denied',
        'qc_received',
        'observations',
        'created_at',
        'updated_at',
    ];

    /**
     * Columnas del detalle: [columna, encabezado, numérica].
     *
     * @var list<array{0: string, 1: string, 2: bool}>
     */
    private const DETAIL_COLUMNS = [
        ['case_code', 'Código del caso', false],
        ['started_on', 'Fecha de inicio', false],
        ['service_on', 'Fecha de servicio', false],
        ['source_type', 'Tipo de ítem', false],
        ['service', 'Servicio', false],
        ['specific_service', 'Ítem', false],
        ['coverage', 'Cobertura', false],
        ['service_status', 'Estatus del servicio', false],
        ['case_status', 'Estatus del caso', false],
        ['patient_name', 'Paciente', false],
        ['patient_document', 'Cédula', false],
        ['business_line', 'Línea de negocio', false],
        ['agency_name', 'Agencia', false],
        ['management_provider', 'Gestionado por', false],
        ['service_provider', 'Proveedor', false],
        ['service_order_number', 'N° de orden', false],
        ['invoice_number', 'N° de factura', false],
        ['net_price', 'Neto (USD)', true],
        ['quoted_amount', 'Cotizado (USD)', true],
        ['invoiced_amount', 'Facturado (USD)', true],
    ];

    /**
     * Fecha del servicio más reciente de un grupo, en formato local.
     */
    private const LAST_START_SQL = "DATE_FORMAT(MAX(started_on), '%d/%m/%Y')";

    /**
     * Columnas del detalle que van al PDF: el horizontal no admite las veinte
     * con letra legible. Excel y CSV siempre traen todas.
     *
     * @var list<string>
     */
    private const DETAIL_PDF_COLUMNS = [
        'case_code',
        'started_on',
        'source_type',
        'specific_service',
        'coverage',
        'service_status',
        'patient_name',
        'patient_document',
        'service_provider',
        'service_order_number',
        'invoiced_amount',
    ];

    /**
     * @return Builder<OperationServiceStatistic>
     */
    public static function query(OperationReportType $type, OperationReportFilters $filters): Builder
    {
        $query = OperationsDashboardMetrics::statisticsQuery();

        if (! $type->usesFilters()) {
            return $query;
        }

        $filters->apply($query);

        if ($type === OperationReportType::DeniedCases) {
            $query->where('case_denied', 'SI');
        }

        return $query;
    }

    /**
     * Servicios que entran en el reporte: el único conteo que hace la pantalla.
     */
    public static function count(OperationReportType $type, OperationReportFilters $filters): int
    {
        return self::query($type, $filters)->count();
    }

    public static function build(OperationReportType $type, OperationReportFilters $filters, ?int $rowLimit = null): OperationReportData
    {
        $criteria = $type->usesFilters() ? $filters->describe() : ['Todos los registros, sin filtros de periodo'];

        return match ($type) {
            OperationReportType::ServiceDetail, OperationReportType::DeniedCases => self::detail($type, $filters, $criteria, $rowLimit),
            OperationReportType::FullTable => self::fullTable($type, $filters, $criteria, $rowLimit),
            OperationReportType::ByStatus => self::grouped($type, $filters, $criteria, ['service_status' => 'Estatus del servicio'], [
                ['COUNT(*)', 'Servicios'],
                ["SUM(coverage = 'Cubierto')", 'Cubiertos'],
                ["SUM(coverage = 'No cubierto')", 'No cubiertos'],
                ['COALESCE(SUM(invoiced_amount), 0)', 'Facturado (USD)'],
            ]),
            OperationReportType::ByProvider => self::grouped($type, $filters, $criteria, [
                'service_provider' => 'Proveedor de servicio',
                'management_provider' => 'Gestionado por',
            ], [
                ['COUNT(*)', 'Servicios'],
                ["SUM(service_status = 'PENDIENTE')", 'Pendientes'],
                ["SUM(service_status = 'EN GESTION')", 'En gestión'],
                ["SUM(service_status = 'FINALIZADO')", 'Finalizados'],
                ['COALESCE(SUM(net_price), 0)', 'Neto (USD)'],
                ['COALESCE(SUM(invoiced_amount), 0)', 'Facturado (USD)'],
            ]),
            OperationReportType::Coverage => self::grouped($type, $filters, $criteria, ['service' => 'Servicio'], [
                ['COUNT(*)', 'Servicios'],
                ["SUM(coverage = 'Cubierto')", 'Cubiertos'],
                ["SUM(coverage = 'No cubierto')", 'No cubiertos'],
                ["SUM(coverage NOT IN ('Cubierto', 'No cubierto') OR coverage IS NULL)", 'Sin dato'],
            ], percentOf: [1, 0, '% cubierto']),
            OperationReportType::Billing => self::grouped($type, $filters, $criteria, ['service' => 'Servicio'], [
                ['COUNT(*)', 'Servicios'],
                ["SUM(invoice_number IS NOT NULL AND TRIM(invoice_number) <> '')", 'Con factura'],
                ["SUM(invoice_number IS NULL OR TRIM(invoice_number) = '')", 'Sin factura'],
                ['COALESCE(SUM(net_price), 0)', 'Neto (USD)'],
                ['COALESCE(SUM(quoted_amount), 0)', 'Cotizado (USD)'],
                ['COALESCE(SUM(discount_amount), 0)', 'Descuento (USD)'],
                ['COALESCE(SUM(invoiced_amount), 0)', 'Facturado (USD)'],
            ]),
            OperationReportType::BusinessLine => self::grouped($type, $filters, $criteria, [
                'business_line' => 'Línea de negocio',
                'agency_name' => 'Agencia',
            ], [
                ['COUNT(*)', 'Servicios'],
                ["SUM(coverage = 'Cubierto')", 'Cubiertos'],
                ["SUM(coverage = 'No cubierto')", 'No cubiertos'],
                ['COALESCE(SUM(invoiced_amount), 0)', 'Facturado (USD)'],
            ]),
            OperationReportType::CasesByPatient => self::grouped($type, $filters, $criteria, [
                'patient_name' => 'Paciente',
                'patient_document' => 'Cédula',
            ], [
                ['COUNT(DISTINCT telemedicine_case_id)', 'Casos'],
                ['COUNT(*)', 'Servicios'],
                ["SUM(coverage = 'Cubierto')", 'Cubiertos'],
                ["SUM(coverage = 'No cubierto')", 'No cubiertos'],
                ['COALESCE(SUM(invoiced_amount), 0)', 'Facturado (USD)'],
            ], attributes: [
                ['MAX(business_line)', 'Línea de negocio'],
                ['MAX(agency_name)', 'Agencia'],
                [self::LAST_START_SQL, 'Último servicio'],
            ], rowLimit: $rowLimit),
            OperationReportType::Patients => self::grouped($type, $filters, $criteria, [
                'patient_name' => 'Paciente',
                'patient_document' => 'Cédula',
            ], [
                ['COUNT(DISTINCT telemedicine_case_id)', 'Casos'],
                ['COUNT(*)', 'Servicios'],
            ], attributes: [
                ["DATE_FORMAT(MAX(patient_birth_date), '%d/%m/%Y')", 'Fecha de nacimiento'],
                ['MAX(patient_age)', 'Edad'],
                ['MAX(patient_relationship)', 'Parentesco'],
                ['MAX(plan_holder_name)', 'Titular'],
                ['MAX(plan_holder_document)', 'Cédula del titular'],
                ['MAX(contractor)', 'Contratante'],
                ['MAX(business_line)', 'Línea de negocio'],
                ['MAX(agency_name)', 'Agencia'],
                ['MAX(patient_phone)', 'Teléfono'],
                ['MAX(patient_email)', 'Correo'],
                ['MAX(state)', 'Estado'],
                ['MAX(city)', 'Ciudad'],
                [self::LAST_START_SQL, 'Último servicio'],
            ], rowLimit: $rowLimit, pdfHeadings: [
                'Paciente', 'Cédula', 'Edad', 'Titular', 'Línea de negocio', 'Agencia', 'Teléfono', 'Estado', 'Último servicio', 'Casos', 'Servicios',
            ]),
            OperationReportType::CasesByBusinessLine => self::grouped($type, $filters, $criteria, [
                'business_line' => 'Línea de negocio',
            ], [
                ['COUNT(DISTINCT telemedicine_case_id)', 'Casos'],
                ["COUNT(DISTINCT NULLIF(TRIM(patient_document), ''))", 'Pacientes'],
                ['COUNT(*)', 'Servicios'],
                ["SUM(coverage = 'Cubierto')", 'Cubiertos'],
                ["SUM(coverage = 'No cubierto')", 'No cubiertos'],
                ['COALESCE(SUM(invoiced_amount), 0)', 'Facturado (USD)'],
            ]),
        };
    }

    public static function sourceTypeLabel(?string $sourceType): string
    {
        return match ($sourceType) {
            OperationServiceStatistic::SOURCE_LAB => 'Laboratorio',
            OperationServiceStatistic::SOURCE_MEDICATION => 'Medicamento',
            OperationServiceStatistic::SOURCE_STUDY => 'Estudio',
            OperationServiceStatistic::SOURCE_SPECIALTY => 'Especialista',
            OperationServiceStatistic::SOURCE_AMBULANCE => 'Ambulancia',
            OperationServiceStatistic::SOURCE_CLINIC_ADMISSION => 'Ingreso a clínica',
            OperationServiceStatistic::SOURCE_TPA_RETAIL => 'TPA/RETAIL',
            default => (string) $sourceType,
        };
    }

    /**
     * @param  list<string>  $criteria
     */
    private static function detail(OperationReportType $type, OperationReportFilters $filters, array $criteria, ?int $rowLimit): OperationReportData
    {
        $columns = array_column(self::DETAIL_COLUMNS, 0);
        $numeric = array_keys(array_filter(array_column(self::DETAIL_COLUMNS, 2)));

        $rows = (function () use ($type, $filters, $columns, $rowLimit): Generator {
            foreach (self::lazyRows(self::query($type, $filters), ['id', ...$columns], $rowLimit) as $record) {
                $row = [];

                foreach ($columns as $column) {
                    $value = $record->{$column} ?? null;
                    $row[] = match ($column) {
                        'source_type' => self::sourceTypeLabel($value),
                        'started_on', 'service_on' => self::formatDate($value),
                        'net_price', 'quoted_amount', 'invoiced_amount' => $value === null ? null : round((float) $value, 2),
                        default => $value,
                    };
                }

                yield $row;
            }
        })();

        return new OperationReportData(
            title: $type->label(),
            headings: array_column(self::DETAIL_COLUMNS, 1),
            rows: $rows,
            numericColumns: array_values($numeric),
            criteria: $criteria,
            pdfColumns: array_values(array_keys(array_intersect($columns, self::DETAIL_PDF_COLUMNS))),
        );
    }

    /**
     * @param  list<string>  $criteria
     */
    private static function fullTable(OperationReportType $type, OperationReportFilters $filters, array $criteria, ?int $rowLimit): OperationReportData
    {
        $rows = (function () use ($type, $filters, $rowLimit): Generator {
            foreach (self::lazyRows(self::query($type, $filters), self::FULL_TABLE_COLUMNS, $rowLimit) as $record) {
                yield array_map(
                    static fn (string $column): mixed => $record->{$column} ?? null,
                    self::FULL_TABLE_COLUMNS,
                );
            }
        })();

        return new OperationReportData(
            title: $type->label(),
            headings: self::FULL_TABLE_COLUMNS,
            rows: $rows,
            criteria: $criteria,
        );
    }

    /**
     * Resumen agrupado: una consulta para los grupos y otra, sin agrupar, para
     * la fila de totales. Los totales salen de la base y no de sumar filas, así
     * que los conteos distintos (casos, pacientes) no se duplican cuando un
     * mismo caso o paciente aparece en más de un grupo.
     *
     * @param  array<string, string>  $groupBy  columna => encabezado
     * @param  list<array{0: string, 1: string}>  $metrics  [expresión SQL fija, encabezado]
     * @param  array{0: int, 1: int, 2: string}|null  $percentOf  [métrica numerador, métrica denominador, encabezado]
     * @param  list<string>  $criteria
     * @param  list<array{0: string, 1: string}>  $attributes  [expresión SQL fija, encabezado]: texto descriptivo, sin total
     * @param  list<string>|null  $pdfHeadings  Encabezados que van al PDF; null = todos
     */
    private static function grouped(
        OperationReportType $type,
        OperationReportFilters $filters,
        array $criteria,
        array $groupBy,
        array $metrics,
        ?array $percentOf = null,
        array $attributes = [],
        ?int $rowLimit = null,
        ?array $pdfHeadings = null,
    ): OperationReportData {
        $selects = [];
        $groupAliases = [];

        foreach (array_keys($groupBy) as $index => $column) {
            $alias = 'g'.$index;
            $groupAliases[] = $alias;
            $selects[] = "COALESCE(NULLIF(TRIM({$column}), ''), 'Sin dato') AS {$alias}";
        }

        foreach ($attributes as $index => [$expression]) {
            $selects[] = "{$expression} AS a{$index}";
        }

        $metricSelects = [];

        foreach ($metrics as $index => [$expression]) {
            $metricSelects[] = "{$expression} AS m{$index}";
        }

        $results = self::query($type, $filters)
            ->toBase()
            ->selectRaw(implode(', ', [...$selects, ...$metricSelects]))
            ->groupBy($groupAliases)
            ->orderByDesc('m0')
            ->orderBy('g0')
            ->get();

        $metricCount = count($metrics);
        $rows = [];

        foreach ($results as $result) {
            $row = [];

            foreach ($groupAliases as $alias) {
                $row[] = (string) $result->{$alias};
            }

            foreach (array_keys($attributes) as $index) {
                $row[] = $result->{'a'.$index} === null ? null : (string) $result->{'a'.$index};
            }

            foreach (range(0, $metricCount - 1) as $index) {
                $row[] = self::number((float) $result->{'m'.$index});
            }

            if ($percentOf !== null) {
                $row[] = self::percent((float) $result->{'m'.$percentOf[0]}, (float) $result->{'m'.$percentOf[1]});
            }

            $rows[] = $row;
        }

        $headings = [...array_values($groupBy), ...array_column($attributes, 1), ...array_column($metrics, 1)];
        $totalRow = null;

        if ($rows !== []) {
            $totals = self::query($type, $filters)->toBase()->selectRaw(implode(', ', $metricSelects))->first();
            $totalRow = ['TOTAL', ...array_fill(0, count($groupBy) + count($attributes) - 1, '')];

            foreach (range(0, $metricCount - 1) as $index) {
                $totalRow[] = self::number((float) ($totals?->{'m'.$index} ?? 0));
            }

            if ($percentOf !== null) {
                $totalRow[] = self::percent((float) ($totals?->{'m'.$percentOf[0]} ?? 0), (float) ($totals?->{'m'.$percentOf[1]} ?? 0));
            }
        }

        if ($percentOf !== null) {
            $headings[] = $percentOf[2];
        }

        $firstNumeric = count($groupBy) + count($attributes);

        return new OperationReportData(
            title: $type->label(),
            headings: $headings,
            rows: $rowLimit === null ? $rows : array_slice($rows, 0, $rowLimit),
            totals: $totalRow,
            numericColumns: range($firstNumeric, count($headings) - 1),
            criteria: $criteria,
            pdfColumns: $pdfHeadings === null ? null : array_values(array_keys(array_intersect($headings, $pdfHeadings))),
            totalRows: count($rows),
        );
    }

    /**
     * @param  Builder<OperationServiceStatistic>  $query
     * @param  list<string>  $columns
     * @return iterable<int, object>
     */
    private static function lazyRows(Builder $query, array $columns, ?int $rowLimit): iterable
    {
        $base = $query->toBase()->select(array_map(
            static fn (string $column): string => 'operation_service_statistics.'.$column,
            $columns,
        ));

        if ($rowLimit !== null) {
            return $base->orderBy('operation_service_statistics.id')->limit($rowLimit)->get();
        }

        return $base->lazyById(self::CHUNK_SIZE, 'operation_service_statistics.id', 'id');
    }

    private static function formatDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $date = substr((string) $value, 0, 10);

        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) === 1
            ? $parts[3].'/'.$parts[2].'/'.$parts[1]
            : $date;
    }

    private static function number(float $value): int|float
    {
        return floor($value) === $value && abs($value) < PHP_INT_MAX ? (int) $value : round($value, 2);
    }

    private static function percent(float $part, float $whole): float
    {
        return $whole > 0 ? round($part * 100 / $whole, 2) : 0.0;
    }
}
