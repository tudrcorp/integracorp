<?php

declare(strict_types=1);

namespace App\Support\Operations\Reports;

use App\Enums\OperationReportType;
use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceOrderItem;
use App\Models\TelemedicinePatientMedications;
use App\Support\Operations\ClinicalServiceCatalog;
use App\Support\Operations\QuoteLinePricing;
use App\Support\Telemedicine\TelemedicineMedicationCoverage;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Medicamentos asignados por el médico a cada caso: detalle sin indicaciones,
 * costos de cotización y de orden de servicio, y conciliación por caso/paciente.
 */
final class CaseMedicationReportBuilder
{
    public const COVERAGE_COVERED = 'Cubierto';

    public const COVERAGE_UNCOVERED = 'No cubierto';

    /**
     * Columnas del detalle que caben en el PDF horizontal.
     *
     * @var list<int>
     */
    private const PDF_COLUMNS = [0, 1, 2, 3, 4, 8, 13, 15, 16, 19];

    private const CHUNK = 250;

    /** Tope de filas de la conciliación en PDF para no saturar DomPDF. */
    private const PDF_RECONCILIATION_LIMIT = 200;

    /**
     * @return array<string, string>
     */
    public static function coverageOptions(): array
    {
        return [
            self::COVERAGE_COVERED => self::COVERAGE_COVERED,
            self::COVERAGE_UNCOVERED => self::COVERAGE_UNCOVERED,
        ];
    }

    /**
     * @return Builder<TelemedicinePatientMedications>
     */
    public static function query(OperationReportFilters $filters): Builder
    {
        $query = TelemedicinePatientMedications::query()
            ->whereBetween('telemedicine_patient_medications.created_at', [
                $filters->from->startOfDay(),
                $filters->to->endOfDay(),
            ]);

        $coverages = array_values(array_intersect($filters->coverages, array_keys(self::coverageOptions())));

        if ($coverages === [self::COVERAGE_COVERED]) {
            return TelemedicineMedicationCoverage::whereCovered($query);
        }

        if ($coverages === [self::COVERAGE_UNCOVERED]) {
            return $query->whereNot(function (Builder $covered): void {
                TelemedicineMedicationCoverage::whereCovered($covered);
            });
        }

        return $query;
    }

    public static function count(OperationReportFilters $filters): int
    {
        return self::query($filters)->count();
    }

    public static function build(OperationReportType $type, OperationReportFilters $filters, ?int $rowLimit = null): OperationReportData
    {
        $summary = self::summary($filters);
        $reconciliation = self::reconciliationRows(
            $filters,
            $summary,
            $rowLimit === null ? null : self::PDF_RECONCILIATION_LIMIT,
        );

        $rowsQuery = self::query($filters)
            ->select([
                'telemedicine_patient_medications.id',
                'telemedicine_patient_medications.telemedicine_patient_id',
                'telemedicine_patient_medications.telemedicine_case_id',
                'telemedicine_patient_medications.telemedicine_doctor_id',
                'telemedicine_patient_medications.telemedicine_priority_id',
                'telemedicine_patient_medications.operation_inventory_id',
                'telemedicine_patient_medications.operation_coordination_service_id',
                'telemedicine_patient_medications.medicine',
                'telemedicine_patient_medications.quantity',
                'telemedicine_patient_medications.duration',
                'telemedicine_patient_medications.status',
                'telemedicine_patient_medications.is_covered',
                'telemedicine_patient_medications.created_at',
            ])
            ->with([
                'telemedicineCase:id,code',
                'telemedicinePatient:id,full_name,nro_identificacion',
                'telemedicineDoctor:id,full_name',
                'telemedicinePriority:id,name',
                'operationInventory:id,name,unit,concentration,laboratory,barcode,is_covered,operation_inventory_principle_active_id',
                'operationInventory.operationInventoryPrincipleActive:id,name',
            ])
            ->orderBy('telemedicine_patient_medications.telemedicine_case_id')
            ->orderBy('telemedicine_patient_medications.id');

        if ($rowLimit !== null) {
            $rowsQuery->limit($rowLimit);
        }

        $rows = (static function () use ($rowsQuery): Generator {
            $buffer = [];

            foreach ($rowsQuery->lazyById(self::CHUNK) as $medication) {
                $buffer[] = $medication;

                if (count($buffer) >= self::CHUNK) {
                    yield from self::mapChunk($buffer);
                    $buffer = [];
                }
            }

            if ($buffer !== []) {
                yield from self::mapChunk($buffer);
            }
        })();

        $totals = $summary['count'] === 0 ? null : [
            'TOTAL',
            $summary['cases'].' '.($summary['cases'] === 1 ? 'caso' : 'casos'),
            $summary['patients'].' '.($summary['patients'] === 1 ? 'paciente' : 'pacientes'),
            '',
            $summary['count'].' '.($summary['count'] === 1 ? 'medicamento' : 'medicamentos'),
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            $summary['covered'].' cub. / '.$summary['uncovered'].' no cub.',
            '',
            '',
            $summary['quote_cost_usd'],
            $summary['quote_sale_usd'],
            '',
            $summary['order_cost_usd'],
        ];

        return new OperationReportData(
            title: $type->label(),
            headings: [
                'Caso',
                'Paciente',
                'Cédula',
                'Asignado el',
                'Medicamento',
                'Principio activo',
                'Concentración',
                'Laboratorio',
                'Cantidad',
                'Unidad',
                'Duración',
                'Prioridad',
                'Estatus',
                'Cobertura',
                'Origen',
                'Doctor',
                'Costo cotización (US$)',
                'Precio venta cotización (US$)',
                'N.º orden de servicio',
                'Costo orden de servicio (US$)',
            ],
            rows: $rows,
            totals: $totals,
            numericColumns: [8, 16, 17, 19],
            criteria: [...self::criteria($filters), ...self::summaryLines($summary)],
            pdfColumns: self::PDF_COLUMNS,
            totalRows: $summary['count'],
            sections: $reconciliation === [] ? [] : [[
                'title' => 'Conciliación por caso y paciente',
                'headings' => [
                    'Caso',
                    'Paciente',
                    'Cédula',
                    'Medicamentos',
                    'Cubiertos',
                    'No cubiertos',
                    'Costo cotización (US$)',
                    'Precio venta cotización (US$)',
                    'Costo orden de servicio (US$)',
                ],
                'rows' => $reconciliation,
                'totals' => [
                    'TOTAL',
                    $summary['cases'].' '.($summary['cases'] === 1 ? 'caso' : 'casos'),
                    $summary['patients'].' '.($summary['patients'] === 1 ? 'paciente' : 'pacientes'),
                    $summary['count'],
                    $summary['covered'],
                    $summary['uncovered'],
                    $summary['quote_cost_usd'],
                    $summary['quote_sale_usd'],
                    $summary['order_cost_usd'],
                ],
                'numericColumns' => [3, 4, 5, 6, 7, 8],
            ]],
        );
    }

    /**
     * @return array{
     *     count: int,
     *     covered: int,
     *     uncovered: int,
     *     cases: int,
     *     patients: int,
     *     quote_cost_usd: float,
     *     quote_sale_usd: float,
     *     order_cost_usd: float
     * }
     */
    public static function summary(OperationReportFilters $filters): array
    {
        $coveredCount = TelemedicineMedicationCoverage::whereCovered(self::query($filters))->count();
        $total = self::count($filters);

        $cases = (int) self::query($filters)
            ->toBase()
            ->selectRaw('COUNT(DISTINCT telemedicine_case_id) AS total')
            ->value('total');

        $patients = (int) self::query($filters)
            ->toBase()
            ->selectRaw('COUNT(DISTINCT telemedicine_patient_id) AS total')
            ->value('total');

        $costs = self::aggregateCosts($filters);

        return [
            'count' => $total,
            'covered' => $coveredCount,
            'uncovered' => max(0, $total - $coveredCount),
            'cases' => $cases,
            'patients' => $patients,
            'quote_cost_usd' => $costs['quote_cost_usd'],
            'quote_sale_usd' => $costs['quote_sale_usd'],
            'order_cost_usd' => $costs['order_cost_usd'],
        ];
    }

    /**
     * @param  list<TelemedicinePatientMedications>  $medications
     * @return list<list<scalar|null>>
     */
    private static function mapChunk(array $medications): array
    {
        $ids = array_map(static fn (TelemedicinePatientMedications $medication): int => (int) $medication->id, $medications);
        $coordinationIds = array_values(array_unique(array_filter(array_map(
            static fn (TelemedicinePatientMedications $medication): ?int => filled($medication->operation_coordination_service_id)
                ? (int) $medication->operation_coordination_service_id
                : null,
            $medications,
        ))));

        $quoteCosts = self::quoteCostMap($coordinationIds);
        $orderCosts = self::orderCostMap($ids);
        $rows = [];

        foreach ($medications as $medication) {
            $inventory = $medication->operationInventory;
            $covered = TelemedicineMedicationCoverage::isCovered($medication);
            $quote = $quoteCosts[(int) $medication->id] ?? null;
            $order = $orderCosts[(int) $medication->id] ?? null;

            $rows[] = [
                $medication->telemedicineCase?->code,
                $medication->telemedicinePatient?->full_name,
                $medication->telemedicinePatient?->nro_identificacion,
                $medication->created_at?->format('d/m/Y H:i'),
                $inventory?->name ?: $medication->medicine,
                $inventory?->operationInventoryPrincipleActive?->name,
                $inventory?->concentration,
                $inventory?->laboratory,
                is_numeric($medication->quantity) ? (int) $medication->quantity : $medication->quantity,
                $inventory?->unit,
                $medication->duration,
                $medication->telemedicinePriority?->name,
                $medication->status,
                $covered ? self::COVERAGE_COVERED : self::COVERAGE_UNCOVERED,
                TelemedicineMedicationCoverage::originLabel($medication),
                $medication->telemedicineDoctor?->full_name,
                $quote['cost'] ?? null,
                $quote['sale'] ?? null,
                $order['order_number'] ?? null,
                $order['amount'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<int>  $coordinationIds
     * @return array<int, array{cost: float, sale: float, status: string|null}>
     */
    private static function quoteCostMap(array $coordinationIds): array
    {
        if ($coordinationIds === []) {
            return [];
        }

        $quotes = OperationQuoteGenerator::query()
            ->whereIn('operation_coordination_service_id', $coordinationIds)
            ->where('type_service', 'MEDICAMENTOS')
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [OperationQuoteGenerator::STATUS_APPROVED])
            ->orderByDesc('id')
            ->get(['id', 'operation_coordination_service_id', 'status', 'items']);

        $map = [];

        foreach ($quotes as $quote) {
            foreach ((array) $quote->items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $key = (string) ($item['key'] ?? '');

                if (ClinicalServiceCatalog::kindFromKey($key) !== 'medication') {
                    continue;
                }

                $medicationId = ClinicalServiceCatalog::idFromKey($key);

                if ($medicationId <= 0 || isset($map[$medicationId])) {
                    continue;
                }

                $cost = is_numeric($item['unit_price_usd'] ?? null) ? round((float) $item['unit_price_usd'], 2) : null;
                $sale = $cost === null ? null : QuoteLinePricing::salePrice($cost, $item['profit_percentage'] ?? null);

                if ($cost === null && $sale === null) {
                    continue;
                }

                $map[$medicationId] = [
                    'cost' => $cost ?? 0.0,
                    'sale' => $sale ?? ($cost ?? 0.0),
                    'status' => $quote->status,
                ];
            }
        }

        return $map;
    }

    /**
     * @param  list<int>  $medicationIds
     * @return array<int, array{amount: float|null, order_number: string|null}>
     */
    private static function orderCostMap(array $medicationIds): array
    {
        if ($medicationIds === []) {
            return [];
        }

        $items = OperationServiceOrderItem::query()
            ->whereIn('telemedicine_patient_medication_id', $medicationIds)
            ->with('operationServiceOrder:id,order_number,total_amount_usd')
            ->orderByDesc('id')
            ->get(['id', 'telemedicine_patient_medication_id', 'amount', 'operation_service_order_id']);

        $map = [];

        foreach ($items as $item) {
            $medicationId = (int) $item->telemedicine_patient_medication_id;

            if ($medicationId <= 0 || isset($map[$medicationId])) {
                continue;
            }

            $amount = is_numeric($item->amount) ? round((float) $item->amount, 2) : null;

            $map[$medicationId] = [
                'amount' => $amount,
                'order_number' => $item->operationServiceOrder?->order_number,
            ];
        }

        return $map;
    }

    /**
     * @return array{quote_cost_usd: float, quote_sale_usd: float, order_cost_usd: float}
     */
    private static function aggregateCosts(OperationReportFilters $filters): array
    {
        $quoteCost = 0.0;
        $quoteSale = 0.0;
        $orderCost = 0.0;

        self::query($filters)
            ->select(['id', 'operation_coordination_service_id'])
            ->orderBy('id')
            ->chunkById(self::CHUNK, function (Collection $chunk) use (&$quoteCost, &$quoteSale, &$orderCost): void {
                $ids = $chunk->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
                $coordinationIds = $chunk->pluck('operation_coordination_service_id')
                    ->filter()
                    ->map(fn (mixed $id): int => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                $quotes = self::quoteCostMap($coordinationIds);
                $orders = self::orderCostMap($ids);

                foreach ($ids as $id) {
                    if (isset($quotes[$id])) {
                        $quoteCost += $quotes[$id]['cost'];
                        $quoteSale += $quotes[$id]['sale'];
                    }

                    if (isset($orders[$id]['amount'])) {
                        $orderCost += (float) $orders[$id]['amount'];
                    }
                }
            });

        return [
            'quote_cost_usd' => round($quoteCost, 2),
            'quote_sale_usd' => round($quoteSale, 2),
            'order_cost_usd' => round($orderCost, 2),
        ];
    }

    /**
     * @param  array{count: int, covered: int, uncovered: int, cases: int, patients: int, quote_cost_usd: float, quote_sale_usd: float, order_cost_usd: float}  $summary
     * @return list<list<scalar|null>>
     */
    private static function reconciliationRows(OperationReportFilters $filters, array $summary, ?int $rowLimit = null): array
    {
        if ($summary['count'] === 0) {
            return [];
        }

        /** @var array<string, array{case: ?string, patient: ?string, document: ?string, total: int, covered: int, uncovered: int, quote_cost: float, quote_sale: float, order_cost: float}> $groups */
        $groups = [];

        self::query($filters)
            ->select([
                'telemedicine_patient_medications.id',
                'telemedicine_patient_medications.telemedicine_patient_id',
                'telemedicine_patient_medications.telemedicine_case_id',
                'telemedicine_patient_medications.operation_inventory_id',
                'telemedicine_patient_medications.operation_coordination_service_id',
                'telemedicine_patient_medications.is_covered',
            ])
            ->with([
                'telemedicineCase:id,code',
                'telemedicinePatient:id,full_name,nro_identificacion',
                'operationInventory:id,is_covered',
            ])
            ->orderBy('id')
            ->chunkById(self::CHUNK, function (Collection $chunk) use (&$groups): void {
                $ids = $chunk->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
                $coordinationIds = $chunk->pluck('operation_coordination_service_id')
                    ->filter()
                    ->map(fn (mixed $id): int => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                $quotes = self::quoteCostMap($coordinationIds);
                $orders = self::orderCostMap($ids);

                foreach ($chunk as $medication) {
                    /** @var TelemedicinePatientMedications $medication */
                    $key = ((int) $medication->telemedicine_case_id).'|'.((int) $medication->telemedicine_patient_id);

                    if (! isset($groups[$key])) {
                        $groups[$key] = [
                            'case' => $medication->telemedicineCase?->code,
                            'patient' => $medication->telemedicinePatient?->full_name,
                            'document' => $medication->telemedicinePatient?->nro_identificacion,
                            'total' => 0,
                            'covered' => 0,
                            'uncovered' => 0,
                            'quote_cost' => 0.0,
                            'quote_sale' => 0.0,
                            'order_cost' => 0.0,
                        ];
                    }

                    $groups[$key]['total']++;

                    if (TelemedicineMedicationCoverage::isCovered($medication)) {
                        $groups[$key]['covered']++;
                    } else {
                        $groups[$key]['uncovered']++;
                    }

                    $medicationId = (int) $medication->id;

                    if (isset($quotes[$medicationId])) {
                        $groups[$key]['quote_cost'] += $quotes[$medicationId]['cost'];
                        $groups[$key]['quote_sale'] += $quotes[$medicationId]['sale'];
                    }

                    if (isset($orders[$medicationId]['amount'])) {
                        $groups[$key]['order_cost'] += (float) $orders[$medicationId]['amount'];
                    }
                }
            });

        uasort($groups, static function (array $a, array $b): int {
            return [$a['case'] ?? '', $a['patient'] ?? ''] <=> [$b['case'] ?? '', $b['patient'] ?? ''];
        });

        $rows = [];

        foreach ($groups as $group) {
            $rows[] = [
                $group['case'],
                $group['patient'],
                $group['document'],
                $group['total'],
                $group['covered'],
                $group['uncovered'],
                round($group['quote_cost'], 2),
                round($group['quote_sale'], 2),
                round($group['order_cost'], 2),
            ];

            if ($rowLimit !== null && count($rows) >= $rowLimit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private static function criteria(OperationReportFilters $filters): array
    {
        $lines = ['Asignados del '.$filters->from->format('d/m/Y').' al '.$filters->to->format('d/m/Y')];

        $coverages = array_values(array_intersect($filters->coverages, array_keys(self::coverageOptions())));

        if ($coverages !== []) {
            $lines[] = 'Cobertura: '.implode(', ', $coverages);
        }

        return $lines;
    }

    /**
     * @param  array{count: int, covered: int, uncovered: int, cases: int, patients: int, quote_cost_usd: float, quote_sale_usd: float, order_cost_usd: float}  $summary
     * @return list<string>
     */
    private static function summaryLines(array $summary): array
    {
        $usd = static fn (float $amount): string => 'US$ '.number_format($amount, 2, ',', '.');

        return [
            'Casos: '.$summary['cases'].' · Pacientes: '.$summary['patients']
                .' · Cubiertos: '.$summary['covered'].' · No cubiertos: '.$summary['uncovered'],
            'Costos: cotización '.$usd($summary['quote_cost_usd'])
                .' (venta '.$usd($summary['quote_sale_usd']).')'
                .' · orden de servicio '.$usd($summary['order_cost_usd']),
        ];
    }
}
