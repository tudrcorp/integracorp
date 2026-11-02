<?php

declare(strict_types=1);

namespace App\Support\Operations\Reports;

use App\Enums\OperationReportType;
use App\Models\OperationInventory;
use App\Models\OperationInventoryEntry;
use App\Models\OperationInventoryOutflow;
use App\Models\OperationInventoryProduct;
use App\Models\OperationInventoryProductCategory;
use App\Models\OperationInventoryProductStock;
use App\Models\OperationInventorySetting;
use App\Models\OperationInventoryUbication;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reportes de inventario del generador de Operaciones.
 *
 * Fuentes y reglas, iguales a las del resto del módulo:
 * - La existencia vigente es la del almacén (`operation_inventory_product_stocks`),
 *   la misma que usan la ficha del producto y la alerta de stock bajo.
 * - El libro de movimientos son las entradas y las salidas. Si una fila vieja no
 *   trae producto o ubicación, se toman de su ficha (`operation_inventories`).
 * - Los ajustes (motivo que empieza por «AJUSTE») corrigen existencia: salen del
 *   inventario pero no son consumo.
 * - Los saldos de fechas pasadas se reconstruyen desde la existencia de hoy hacia
 *   atrás con los movimientos registrados. Un saldo negativo delata existencia
 *   cargada sin movimiento: la Conciliación muestra dónde.
 */
final class OperationInventoryReportBuilder
{
    public const DEFAULT_LOW_STOCK_THRESHOLD = 3;

    /**
     * Días hacia atrás para medir el ritmo de consumo del reporte de stock bajo.
     */
    public const CONSUMPTION_WINDOW_DAYS = 30;

    private const IN = 'IN';

    private const OUT = 'OUT';

    /**
     * Columnas del kárdex que caben en el PDF horizontal.
     *
     * @var list<int>
     */
    private const MOVEMENTS_PDF_COLUMNS = [0, 1, 2, 4, 6, 7, 8, 10];

    public static function build(OperationReportType $type, OperationReportFilters $filters, ?int $rowLimit = null): OperationReportData
    {
        return match ($type) {
            OperationReportType::InventoryGeneral => self::general($type, $filters, $rowLimit),
            OperationReportType::InventoryLowStock => self::lowStock($type, $filters, $rowLimit),
            OperationReportType::InventoryMovements => self::movements($type, $filters, $rowLimit),
            OperationReportType::InventoryBalance => self::balance($type, $filters, $rowLimit),
            OperationReportType::InventoryConsumption => self::consumption($type, $filters, $rowLimit),
            OperationReportType::InventoryIdle => self::idle($type, $filters, $rowLimit),
            OperationReportType::InventoryReconciliation => self::reconciliation($type, $filters, $rowLimit),
            default => throw new InvalidArgumentException('«'.$type->label().'» no es un reporte de inventario.'),
        };
    }

    public static function count(OperationReportType $type, OperationReportFilters $filters): int
    {
        if ($type === OperationReportType::InventoryMovements) {
            return self::ledger($filters)->whereBetween('m.created_at', self::periodBounds($filters))->count();
        }

        return (int) self::build($type, $filters)->totalRows;
    }

    public static function lowStockThreshold(): int
    {
        $value = OperationInventorySetting::query()->orderBy('id')->value('low_stock_threshold');

        return $value === null ? self::DEFAULT_LOW_STOCK_THRESHOLD : max(0, (int) $value);
    }

    /*
     * -------------------------------------------------------------------------
     * Reportes
     * -------------------------------------------------------------------------
     */

    private static function general(OperationReportType $type, OperationReportFilters $filters, ?int $rowLimit): OperationReportData
    {
        $ubications = self::ubications($filters);
        $threshold = self::lowStockThreshold();
        $stocks = self::stockMatrix($filters, array_keys($ubications));
        $rows = [];
        $sums = array_fill(0, count($ubications) + 2, 0.0);
        $withoutCost = 0;

        foreach (self::activeProducts($filters) as $product) {
            $perUbication = array_map(static fn (int $ubicationId): int => $stocks[$product->id][$ubicationId] ?? 0, array_keys($ubications));
            $total = array_sum($perUbication);
            $status = self::stockStatus($total, $threshold);

            if ($filters->stockStatuses !== [] && ! in_array($status, $filters->stockStatuses, true)) {
                continue;
            }

            $cost = round((float) $product->cost, 2);
            $value = round($total * $cost, 2);

            if ($cost <= 0) {
                $withoutCost++;
            }

            $rows[] = [
                $product->code,
                $product->name,
                $product->category ?? 'Sin categoría',
                $product->unit,
                $product->presentation,
                ...$perUbication,
                $total,
                $cost,
                $value,
                OperationReportFilters::stockStatusOptions()[$status],
            ];

            foreach ([...$perUbication, $total] as $index => $quantity) {
                $sums[$index] += $quantity;
            }

            $sums[count($ubications) + 1] += $value;
        }

        $fixed = ['Código', 'Producto', 'Categoría', 'Unidad', 'Presentación'];
        $headings = [...$fixed, ...array_values($ubications), 'Existencia total', 'Costo unitario (USD)', 'Valor (USD)', 'Estado'];
        $quantityColumns = range(count($fixed), count($fixed) + count($ubications));
        $totalIndex = count($fixed) + count($ubications);

        $notes = ['Estado: agotado = 0; stock bajo = hasta '.$threshold.' unidades (umbral de Parámetros de inventario).'];

        if ($withoutCost > 0) {
            $notes[] = 'Atención: '.$withoutCost.' de '.count($rows).' productos no tienen costo cargado; el valor total está subestimado.';
        }

        return new OperationReportData(
            title: $type->label(),
            headings: $headings,
            rows: self::limit($rows, $rowLimit),
            totals: $rows === [] ? null : [
                'TOTAL', ...array_fill(0, count($fixed) - 1, ''),
                ...array_map(self::number(...), array_slice($sums, 0, count($ubications) + 1)),
                '',
                round($sums[count($ubications) + 1], 2),
                '',
            ],
            numericColumns: [...$quantityColumns, $totalIndex + 1, $totalIndex + 2],
            criteria: [...self::criteria($type, $filters, $ubications), ...$notes],
            totalRows: count($rows),
        );
    }

    private static function lowStock(OperationReportType $type, OperationReportFilters $filters, ?int $rowLimit): OperationReportData
    {
        $ubications = self::ubications($filters);
        $threshold = self::lowStockThreshold();
        $stocks = self::stockMatrix($filters, array_keys($ubications));
        $now = CarbonImmutable::now();
        $windowStart = $now->subDays(self::CONSUMPTION_WINDOW_DAYS)->startOfDay();
        $consumed = self::consumedByProduct($filters, $windowStart, $now);
        $rows = [];

        foreach (self::activeProducts($filters) as $product) {
            $perUbication = array_map(static fn (int $ubicationId): int => $stocks[$product->id][$ubicationId] ?? 0, array_keys($ubications));
            $total = array_sum($perUbication);

            if ($total > $threshold) {
                continue;
            }

            $outflows = $consumed[$product->id] ?? 0;
            $daily = round($outflows / self::CONSUMPTION_WINDOW_DAYS, 2);
            $coverage = $daily > 0 ? round($total / $daily, 1) : null;

            $rows[] = [
                'sort' => [$total, $coverage ?? PHP_FLOAT_MAX, $product->name],
                'row' => [
                    $product->code,
                    $product->name,
                    $product->category ?? 'Sin categoría',
                    ...$perUbication,
                    $total,
                    $outflows,
                    $daily,
                    $coverage,
                    $total <= 0 ? 'Agotado' : 'Stock bajo',
                ],
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);
        $rows = array_column($rows, 'row');

        $fixed = ['Código', 'Producto', 'Categoría'];
        $first = count($fixed);

        return new OperationReportData(
            title: $type->label(),
            headings: [...$fixed, ...array_values($ubications), 'Existencia total', 'Salidas últimos '.self::CONSUMPTION_WINDOW_DAYS.' días', 'Consumo diario', 'Días de cobertura', 'Estado'],
            rows: self::limit($rows, $rowLimit),
            numericColumns: range($first, $first + count($ubications) + 3),
            criteria: [
                ...self::criteria($type, $filters, $ubications),
                'Incluye productos activos con existencia total de '.$threshold.' unidades o menos (umbral de Parámetros de inventario).',
                'Días de cobertura = existencia ÷ consumo diario de los últimos '.self::CONSUMPTION_WINDOW_DAYS.' días (sin ajustes). Vacío = sin consumo en ese lapso.',
            ],
            totalRows: count($rows),
        );
    }

    private static function movements(OperationReportType $type, OperationReportFilters $filters, ?int $rowLimit): OperationReportData
    {
        $bounds = self::periodBounds($filters);

        $query = self::ledger($filters)
            ->leftJoin('operation_inventory_ubications as u', 'u.id', '=', 'm.ubication_id')
            ->leftJoin('telemedicine_cases as tc', 'tc.id', '=', 'm.case_id')
            ->whereBetween('m.created_at', $bounds)
            ->orderBy('m.created_at')
            ->orderBy('m.direction')
            ->orderBy('m.id')
            ->select([
                'm.direction', 'm.created_at', 'm.quantity', 'm.reason', 'm.observations', 'm.created_by',
                'p.code', 'p.name', 'pc.name as category', 'u.name as ubication', 'tc.code as case_code',
            ]);

        if ($rowLimit !== null) {
            $query->limit($rowLimit);
        }

        $summary = self::ledger($filters)
            ->whereBetween('m.created_at', $bounds)
            ->selectRaw("COALESCE(SUM(CASE WHEN m.direction = 'IN' THEN m.quantity END), 0) AS entradas")
            ->selectRaw("COALESCE(SUM(CASE WHEN m.direction = 'OUT' THEN m.quantity END), 0) AS salidas")
            ->selectRaw('COUNT(*) AS movimientos')
            ->first();

        $in = (int) ($summary->entradas ?? 0);
        $out = (int) ($summary->salidas ?? 0);

        $rows = (static function () use ($query): \Generator {
            foreach ($query->cursor() as $movement) {
                $isEntry = $movement->direction === self::IN;

                yield [
                    self::dateTime($movement->created_at),
                    $isEntry ? 'Entrada' : 'Salida',
                    filled($movement->reason) ? (string) $movement->reason : 'Sin motivo',
                    $movement->code,
                    $movement->name ?? 'Producto eliminado',
                    $movement->category ?? 'Sin categoría',
                    $movement->ubication ?? 'Sin ubicación',
                    $isEntry ? (int) $movement->quantity : -1 * (int) $movement->quantity,
                    $movement->case_code,
                    $movement->observations,
                    $movement->created_by,
                ];
            }
        })();

        return new OperationReportData(
            title: $type->label(),
            headings: ['Fecha y hora', 'Movimiento', 'Motivo', 'Código', 'Producto', 'Categoría', 'Ubicación', 'Cantidad', 'Caso', 'Observaciones', 'Registrado por'],
            rows: $rows,
            totals: (int) ($summary->movimientos ?? 0) === 0 ? null : ['NETO DEL PERIODO', '', '', '', '', '', '', $in - $out, '', '', ''],
            numericColumns: [7],
            criteria: [
                ...self::criteria($type, $filters),
                'Entradas: '.self::format($in).' unidades · Salidas: '.self::format($out).' unidades · Neto: '.self::format($in - $out).' unidades.',
            ],
            pdfColumns: self::MOVEMENTS_PDF_COLUMNS,
            totalRows: (int) ($summary->movimientos ?? 0),
        );
    }

    private static function balance(OperationReportType $type, OperationReportFilters $filters, ?int $rowLimit): OperationReportData
    {
        [$from, $to] = self::periodBounds($filters);

        $flows = self::ledger($filters)
            ->groupBy('m.product_id', 'm.ubication_id')
            ->select(['m.product_id', 'm.ubication_id'])
            ->selectRaw("COALESCE(SUM(CASE WHEN m.direction = 'IN' AND m.created_at >= ? THEN m.quantity END), 0) AS in_since_from", [$from])
            ->selectRaw("COALESCE(SUM(CASE WHEN m.direction = 'OUT' AND m.created_at >= ? THEN m.quantity END), 0) AS out_since_from", [$from])
            ->selectRaw("COALESCE(SUM(CASE WHEN m.direction = 'IN' AND m.created_at > ? THEN m.quantity END), 0) AS in_after_to", [$to])
            ->selectRaw("COALESCE(SUM(CASE WHEN m.direction = 'OUT' AND m.created_at > ? THEN m.quantity END), 0) AS out_after_to", [$to])
            ->get()
            ->keyBy(static fn (object $flow): string => $flow->product_id.':'.$flow->ubication_id);

        $current = self::stockRows($filters);
        $keys = array_unique([...array_keys($current), ...$flows->keys()->all()]);
        $catalog = self::catalog(array_map(static fn (string $key): int => (int) explode(':', $key)[0], $keys));
        $ubications = OperationInventoryUbication::query()->pluck('name', 'id')->all();
        $rows = [];

        foreach ($keys as $key) {
            [$productId, $ubicationId] = array_map('intval', explode(':', $key));
            $product = $catalog[$productId] ?? null;

            if ($product === null) {
                continue;
            }

            $now = $current[$key] ?? 0;
            $flow = $flows->get($key);
            $inSinceFrom = (int) ($flow->in_since_from ?? 0);
            $outSinceFrom = (int) ($flow->out_since_from ?? 0);
            $inAfterTo = (int) ($flow->in_after_to ?? 0);
            $outAfterTo = (int) ($flow->out_after_to ?? 0);

            $opening = $now - $inSinceFrom + $outSinceFrom;
            $entries = $inSinceFrom - $inAfterTo;
            $outflows = $outSinceFrom - $outAfterTo;
            $closing = $now - $inAfterTo + $outAfterTo;

            if ($opening === 0 && $entries === 0 && $outflows === 0 && $closing === 0) {
                continue;
            }

            $rows[] = [
                $product->code,
                $product->name,
                $product->category ?? 'Sin categoría',
                $ubications[$ubicationId] ?? 'Sin ubicación',
                $opening,
                $entries,
                $outflows,
                $closing,
                $opening < 0 || $closing < 0 ? 'Revisar: hay existencia sin movimientos registrados' : '',
            ];
        }

        usort($rows, static fn (array $a, array $b): int => [$a[1], $a[3]] <=> [$b[1], $b[3]]);

        $sum = static fn (int $index): int => array_sum(array_column($rows, $index));

        return new OperationReportData(
            title: $type->label(),
            headings: ['Código', 'Producto', 'Categoría', 'Ubicación', 'Saldo inicial', 'Entradas', 'Salidas', 'Saldo final', 'Observación'],
            rows: self::limit($rows, $rowLimit),
            totals: $rows === [] ? null : ['TOTAL', '', '', '', $sum(4), $sum(5), $sum(6), $sum(7), ''],
            numericColumns: [4, 5, 6, 7],
            criteria: [
                ...self::criteria($type, $filters),
                'Saldos reconstruidos desde la existencia actual del almacén y los movimientos registrados. Saldo final = saldo inicial + entradas − salidas.',
            ],
            totalRows: count($rows),
        );
    }

    private static function consumption(OperationReportType $type, OperationReportFilters $filters, ?int $rowLimit): OperationReportData
    {
        $bounds = self::periodBounds($filters);

        $byReason = self::ledger($filters)
            ->where('m.direction', self::OUT)
            ->whereBetween('m.created_at', $bounds)
            ->groupBy('m.product_id', 'reason_label')
            ->select('m.product_id')
            ->selectRaw("COALESCE(NULLIF(TRIM(m.reason), ''), 'Sin motivo') AS reason_label")
            ->selectRaw('SUM(m.quantity) AS quantity')
            ->get();

        $cases = self::ledger($filters)
            ->where('m.direction', self::OUT)
            ->whereBetween('m.created_at', $bounds)
            ->where('m.case_id', '>', 0)
            ->groupBy('m.product_id')
            ->select('m.product_id')
            ->selectRaw('COUNT(DISTINCT m.case_id) AS cases')
            ->pluck('cases', 'm.product_id')
            ->all();

        $totalCases = (int) self::ledger($filters)
            ->where('m.direction', self::OUT)
            ->whereBetween('m.created_at', $bounds)
            ->where('m.case_id', '>', 0)
            ->distinct()
            ->count('m.case_id');

        $reasons = $byReason->pluck('reason_label')->unique()->sort()->values()->all();
        $catalog = self::catalog($byReason->pluck('product_id')->map(fn (mixed $id): int => (int) $id)->all());
        $days = self::days($filters);
        $matrix = [];

        foreach ($byReason as $line) {
            $matrix[(int) $line->product_id][(string) $line->reason_label] = (int) $line->quantity;
        }

        $rows = [];

        foreach ($matrix as $productId => $quantities) {
            $product = $catalog[$productId] ?? null;
            $perReason = array_map(static fn (string $reason): int => $quantities[$reason] ?? 0, $reasons);
            $total = array_sum($perReason);
            $consumption = array_sum(array_filter(
                $quantities,
                static fn (string $reason): bool => ! self::isAdjustment($reason),
                ARRAY_FILTER_USE_KEY,
            ));

            $rows[] = [
                $product->code ?? '',
                $product->name ?? 'Producto eliminado',
                $product?->category ?? 'Sin categoría',
                ...$perReason,
                $total,
                $consumption,
                round($consumption / $days, 2),
                (int) ($cases[$productId] ?? 0),
            ];
        }

        $consumptionIndex = 3 + count($reasons) + 1;
        usort($rows, static fn (array $a, array $b): int => [$b[$consumptionIndex], $a[1]] <=> [$a[$consumptionIndex], $b[1]]);

        $totals = null;

        if ($rows !== []) {
            $sum = static fn (int $index): int|float => array_sum(array_column($rows, $index));
            $reasonTotals = array_map($sum, range(3, 3 + count($reasons) - 1));
            $totalConsumption = $sum($consumptionIndex);
            $totals = ['TOTAL', '', '', ...$reasonTotals, $sum($consumptionIndex - 1), $totalConsumption, round($totalConsumption / $days, 2), $totalCases];
        }

        return new OperationReportData(
            title: $type->label(),
            headings: ['Código', 'Producto', 'Categoría', ...$reasons, 'Total salidas', 'Consumo (sin ajustes)', 'Consumo diario', 'Casos atendidos'],
            rows: self::limit($rows, $rowLimit),
            totals: $totals,
            numericColumns: range(3, 3 + count($reasons) + 3),
            criteria: [
                ...self::criteria($type, $filters),
                'Una columna por motivo de salida. Los ajustes corrigen existencia y no cuentan como consumo. Consumo diario = consumo ÷ '.$days.' días del periodo.',
            ],
            totalRows: count($rows),
        );
    }

    private static function idle(OperationReportType $type, OperationReportFilters $filters, ?int $rowLimit): OperationReportData
    {
        $ubications = self::ubications($filters);
        $stocks = self::stockMatrix($filters, array_keys($ubications));
        $bounds = self::periodBounds($filters);

        $movedInPeriod = array_flip(self::ledger($filters)
            ->where('m.direction', self::OUT)
            ->whereBetween('m.created_at', $bounds)
            ->whereRaw("COALESCE(m.reason, '') NOT LIKE 'AJUSTE%'")
            ->distinct()
            ->pluck('m.product_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all());

        $lastOutflow = self::ledger($filters)
            ->where('m.direction', self::OUT)
            ->whereRaw("COALESCE(m.reason, '') NOT LIKE 'AJUSTE%'")
            ->groupBy('m.product_id')
            ->select('m.product_id')
            ->selectRaw('MAX(m.created_at) AS last_at')
            ->pluck('last_at', 'm.product_id')
            ->all();

        $today = CarbonImmutable::now()->startOfDay();
        $rows = [];

        foreach (self::activeProducts($filters) as $product) {
            if (isset($movedInPeriod[$product->id])) {
                continue;
            }

            $perUbication = array_map(static fn (int $ubicationId): int => $stocks[$product->id][$ubicationId] ?? 0, array_keys($ubications));
            $total = array_sum($perUbication);

            if ($total <= 0) {
                continue;
            }

            $last = isset($lastOutflow[$product->id]) ? CarbonImmutable::parse((string) $lastOutflow[$product->id]) : null;

            $rows[] = [
                $product->code,
                $product->name,
                $product->category ?? 'Sin categoría',
                ...$perUbication,
                $total,
                round($total * (float) $product->cost, 2),
                $last?->format('d/m/Y') ?? 'Nunca ha salido',
                $last === null ? null : (int) $last->startOfDay()->diffInDays($today),
            ];
        }

        $daysIndex = 3 + count($ubications) + 3;
        usort($rows, static fn (array $a, array $b): int => [$b[$daysIndex] ?? PHP_INT_MAX, $a[1]] <=> [$a[$daysIndex] ?? PHP_INT_MAX, $b[1]]);

        $first = 3;

        return new OperationReportData(
            title: $type->label(),
            headings: ['Código', 'Producto', 'Categoría', ...array_values($ubications), 'Existencia total', 'Valor (USD)', 'Última salida', 'Días sin salida'],
            rows: self::limit($rows, $rowLimit),
            totals: $rows === [] ? null : [
                'TOTAL', '', '',
                ...array_map(static fn (int $index): int => array_sum(array_column($rows, $index)), range($first, $first + count($ubications))),
                round(array_sum(array_column($rows, $first + count($ubications) + 1)), 2),
                '', '',
            ],
            numericColumns: [...range($first, $first + count($ubications) + 1), $daysIndex],
            criteria: [
                ...self::criteria($type, $filters, $ubications),
                'Productos activos con existencia y sin salidas (sin contar ajustes) en el periodo. Ordenados por más días sin salir.',
            ],
            totalRows: count($rows),
        );
    }

    private static function reconciliation(OperationReportType $type, OperationReportFilters $filters, ?int $rowLimit): OperationReportData
    {
        $stocks = self::stockRows($filters);

        $sheets = OperationInventory::query()
            ->toBase()
            ->whereNotNull('operation_inventory_product_id')
            ->whereNotNull('operation_inventory_ubication_id')
            ->when($filters->ubications !== [], fn (QueryBuilder $query) => $query->whereIn('operation_inventory_ubication_id', $filters->ubications))
            ->when($filters->products !== [], fn (QueryBuilder $query) => $query->whereIn('operation_inventory_product_id', $filters->products))
            ->groupBy('operation_inventory_product_id', 'operation_inventory_ubication_id')
            ->select(['operation_inventory_product_id', 'operation_inventory_ubication_id'])
            ->selectRaw('SUM(existence) AS existence')
            ->get()
            ->mapWithKeys(static fn (object $row): array => [$row->operation_inventory_product_id.':'.$row->operation_inventory_ubication_id => (int) $row->existence])
            ->all();

        $ledger = self::ledger($filters)
            ->groupBy('m.product_id', 'm.ubication_id')
            ->select(['m.product_id', 'm.ubication_id'])
            ->selectRaw("COALESCE(SUM(CASE WHEN m.direction = 'IN' THEN m.quantity ELSE -m.quantity END), 0) AS balance")
            ->get()
            ->mapWithKeys(static fn (object $row): array => [$row->product_id.':'.$row->ubication_id => (int) $row->balance])
            ->all();

        $keys = array_unique([...array_keys($stocks), ...array_keys($sheets), ...array_keys($ledger)]);
        $catalog = self::catalog(array_map(static fn (string $key): int => (int) explode(':', $key)[0], $keys));
        $ubications = OperationInventoryUbication::query()->pluck('name', 'id')->all();
        $rows = [];
        $findings = ['sheet' => 0, 'unbacked' => 0, 'missing' => 0];

        foreach ($keys as $key) {
            [$productId, $ubicationId] = array_map('intval', explode(':', $key));
            $product = $catalog[$productId] ?? null;

            if (
                $product === null
                || ($filters->ubications !== [] && ! in_array($ubicationId, $filters->ubications, true))
                || ($filters->categories !== [] && ! in_array((int) $product->category_id, $filters->categories, true))
            ) {
                continue;
            }

            $warehouse = $stocks[$key] ?? null;
            $sheet = $sheets[$key] ?? null;
            $movements = $ledger[$key] ?? 0;
            $issues = [];

            if ($warehouse === null) {
                $issues[] = 'Sin registro en el almacén';
                $findings['missing']++;
            } elseif ($sheet === null) {
                $issues[] = 'Sin ficha de inventario';
                $findings['missing']++;
            } elseif ($warehouse !== $sheet) {
                $issues[] = 'Almacén y ficha no coinciden';
                $findings['sheet']++;
            }

            $unbacked = ($warehouse ?? 0) - $movements;

            if ($unbacked !== 0) {
                $issues[] = $unbacked > 0 ? 'Existencia sin entradas que la respalden' : 'Hay más salidas que existencia';
                $findings['unbacked']++;
            }

            if ($issues === []) {
                continue;
            }

            $rows[] = [
                $product->code,
                $product->name,
                $ubications[$ubicationId] ?? 'Sin ubicación',
                $warehouse,
                $sheet,
                $warehouse !== null && $sheet !== null ? $warehouse - $sheet : null,
                $movements,
                $unbacked,
                implode('; ', $issues),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => [abs((int) $b[5]), abs($b[7]), $a[1]] <=> [abs((int) $a[5]), abs($a[7]), $b[1]]);

        return new OperationReportData(
            title: $type->label(),
            headings: ['Código', 'Producto', 'Ubicación', 'Existencia en almacén', 'Existencia en ficha', 'Diferencia almacén − ficha', 'Saldo según movimientos', 'Diferencia sin respaldo', 'Hallazgo'],
            rows: self::limit($rows, $rowLimit),
            numericColumns: [3, 4, 5, 6, 7],
            criteria: [
                ...self::criteria($type, $filters),
                'Solo se listan las combinaciones con algún hallazgo. Almacén = existencia vigente; ficha = inventario por ubicación; movimientos = entradas − salidas registradas desde siempre.',
                'Hallazgos: '.$findings['sheet'].' con almacén y ficha distintos · '.$findings['unbacked'].' sin respaldo en movimientos · '.$findings['missing'].' sin almacén o sin ficha.',
            ],
            totalRows: count($rows),
        );
    }

    /*
     * -------------------------------------------------------------------------
     * Fuentes
     * -------------------------------------------------------------------------
     */

    /**
     * Libro de movimientos unificado (entradas y salidas), con el producto y la
     * categoría ya unidos y los filtros de producto, categoría y ubicación
     * aplicados. Alias `m` (movimiento), `p` (producto), `pc` (categoría).
     */
    private static function ledger(OperationReportFilters $filters): QueryBuilder
    {
        $entries = OperationInventoryEntry::query()
            ->toBase()
            ->from('operation_inventory_entries as e')
            ->leftJoin('operation_inventories as i', 'i.id', '=', 'e.operation_inventory_id')
            ->selectRaw("'".self::IN."' AS direction, e.id, e.created_at, e.quantity, e.type_entry AS reason, NULL AS case_id, NULL AS observations, e.created_by")
            ->selectRaw('COALESCE(e.operation_inventory_product_id, i.operation_inventory_product_id) AS product_id')
            ->selectRaw('COALESCE(e.operation_inventory_ubication_id, i.operation_inventory_ubication_id) AS ubication_id');

        $outflows = OperationInventoryOutflow::query()
            ->toBase()
            ->from('operation_inventory_outflows as o')
            ->leftJoin('operation_inventories as i', 'i.id', '=', 'o.operation_inventory_id')
            ->selectRaw("'".self::OUT."' AS direction, o.id, o.created_at, o.quantity, o.type_entry AS reason, o.telemedicine_case_id AS case_id, o.observations, o.created_by")
            ->selectRaw('COALESCE(o.operation_inventory_product_id, i.operation_inventory_product_id) AS product_id')
            ->selectRaw('COALESCE(o.operation_inventory_ubication_id, i.operation_inventory_ubication_id) AS ubication_id');

        return DB::query()
            ->fromSub($entries->unionAll($outflows), 'm')
            ->leftJoin('operation_inventory_products as p', 'p.id', '=', 'm.product_id')
            ->leftJoin('operation_inventory_product_categories as pc', 'pc.id', '=', 'p.operation_inventory_product_category_id')
            ->when($filters->ubications !== [], fn (QueryBuilder $query) => $query->whereIn('m.ubication_id', $filters->ubications))
            ->when($filters->products !== [], fn (QueryBuilder $query) => $query->whereIn('m.product_id', $filters->products))
            ->when($filters->categories !== [], fn (QueryBuilder $query) => $query->whereIn('p.operation_inventory_product_category_id', $filters->categories));
    }

    /**
     * Productos activos del catálogo, con su categoría, ordenados por nombre.
     *
     * @return list<object{id: int, code: string, name: string, category: ?string, unit: ?string, presentation: ?string, cost: mixed}>
     */
    private static function activeProducts(OperationReportFilters $filters): array
    {
        return OperationInventoryProduct::query()
            ->toBase()
            ->from('operation_inventory_products as p')
            ->leftJoin('operation_inventory_product_categories as pc', 'pc.id', '=', 'p.operation_inventory_product_category_id')
            ->where('p.is_active', true)
            ->when($filters->categories !== [], fn (QueryBuilder $query) => $query->whereIn('p.operation_inventory_product_category_id', $filters->categories))
            ->when($filters->products !== [], fn (QueryBuilder $query) => $query->whereIn('p.id', $filters->products))
            ->orderBy('p.name')
            ->orderBy('p.id')
            ->get(['p.id', 'p.code', 'p.name', 'pc.name as category', 'p.unit', 'p.presentation', 'p.cost'])
            ->all();
    }

    /**
     * Código, nombre y categoría de los productos pedidos (activos o no).
     *
     * @param  list<int>  $productIds
     * @return array<int, object{id: int, code: string, name: string, category: ?string, cost: mixed, category_id: ?int}>
     */
    private static function catalog(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter($productIds)));

        if ($productIds === []) {
            return [];
        }

        return OperationInventoryProduct::query()
            ->toBase()
            ->from('operation_inventory_products as p')
            ->leftJoin('operation_inventory_product_categories as pc', 'pc.id', '=', 'p.operation_inventory_product_category_id')
            ->whereIn('p.id', $productIds)
            ->get(['p.id', 'p.code', 'p.name', 'pc.name as category', 'p.cost', 'p.operation_inventory_product_category_id as category_id'])
            ->keyBy('id')
            ->all();
    }

    /**
     * Existencia de almacén por producto y ubicación: [producto][ubicación] => unidades.
     *
     * @param  list<int>  $ubicationIds
     * @return array<int, array<int, int>>
     */
    private static function stockMatrix(OperationReportFilters $filters, array $ubicationIds): array
    {
        $matrix = [];

        foreach (self::stockQuery($filters)->whereIn('operation_inventory_ubication_id', $ubicationIds)->get() as $stock) {
            $matrix[(int) $stock->operation_inventory_product_id][(int) $stock->operation_inventory_ubication_id] = (int) $stock->existence;
        }

        return $matrix;
    }

    /**
     * Existencia de almacén por clave «producto:ubicación».
     *
     * @return array<string, int>
     */
    private static function stockRows(OperationReportFilters $filters): array
    {
        $rows = [];

        foreach (self::stockQuery($filters)->get() as $stock) {
            $rows[$stock->operation_inventory_product_id.':'.$stock->operation_inventory_ubication_id] = (int) $stock->existence;
        }

        return $rows;
    }

    private static function stockQuery(OperationReportFilters $filters): QueryBuilder
    {
        return OperationInventoryProductStock::query()
            ->toBase()
            ->from('operation_inventory_product_stocks as s')
            ->join('operation_inventory_products as p', 'p.id', '=', 's.operation_inventory_product_id')
            ->when($filters->ubications !== [], fn (QueryBuilder $query) => $query->whereIn('s.operation_inventory_ubication_id', $filters->ubications))
            ->when($filters->products !== [], fn (QueryBuilder $query) => $query->whereIn('s.operation_inventory_product_id', $filters->products))
            ->when($filters->categories !== [], fn (QueryBuilder $query) => $query->whereIn('p.operation_inventory_product_category_id', $filters->categories))
            ->groupBy('s.operation_inventory_product_id', 's.operation_inventory_ubication_id')
            ->select(['s.operation_inventory_product_id', 's.operation_inventory_ubication_id'])
            ->selectRaw('SUM(s.existence) AS existence');
    }

    /**
     * Unidades consumidas (salidas sin ajustes) por producto entre dos fechas.
     *
     * @return array<int, int>
     */
    private static function consumedByProduct(OperationReportFilters $filters, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return self::ledger($filters)
            ->where('m.direction', self::OUT)
            ->whereBetween('m.created_at', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->whereRaw("COALESCE(m.reason, '') NOT LIKE 'AJUSTE%'")
            ->groupBy('m.product_id')
            ->select('m.product_id')
            ->selectRaw('SUM(m.quantity) AS quantity')
            ->pluck('quantity', 'm.product_id')
            ->map(fn (mixed $quantity): int => (int) $quantity)
            ->all();
    }

    /**
     * Ubicaciones del reporte (las filtradas o todas), id => nombre.
     *
     * @return array<int, string>
     */
    private static function ubications(OperationReportFilters $filters): array
    {
        return OperationInventoryUbication::query()
            ->when($filters->ubications !== [], fn ($query) => $query->whereKey($filters->ubications))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->map(fn (mixed $name): string => (string) $name)
            ->all();
    }

    /*
     * -------------------------------------------------------------------------
     * Utilidades
     * -------------------------------------------------------------------------
     */

    /**
     * Periodo, ubicaciones, categorías y productos en texto, para el encabezado.
     *
     * @param  array<int, string>|null  $ubications
     * @return list<string>
     */
    private static function criteria(OperationReportType $type, OperationReportFilters $filters, ?array $ubications = null): array
    {
        $lines = [$type->usesPeriod()
            ? 'Periodo: '.$filters->from->format('d/m/Y').' al '.$filters->to->format('d/m/Y').' (fecha del movimiento)'
            : 'Existencia al '.CarbonImmutable::now()->format('d/m/Y h:i a')];

        if ($filters->ubications !== []) {
            $lines[] = 'Ubicación: '.implode(', ', array_values($ubications ?? self::ubications($filters)));
        }

        if ($filters->categories !== []) {
            $lines[] = 'Categoría: '.implode(', ', OperationInventoryProductCategory::query()->whereKey($filters->categories)->orderBy('name')->pluck('name')->all());
        }

        if ($filters->products !== []) {
            $names = OperationInventoryProduct::query()->whereKey($filters->products)->orderBy('name')->limit(6)->pluck('name')->all();
            $extra = count($filters->products) - count($names);
            $lines[] = 'Producto: '.implode(', ', $names).($extra > 0 ? ' y '.$extra.' más' : '');
        }

        if ($filters->stockStatuses !== [] && $type === OperationReportType::InventoryGeneral) {
            $lines[] = 'Estado: '.implode(', ', array_map(static fn (string $status): string => OperationReportFilters::stockStatusOptions()[$status], $filters->stockStatuses));
        }

        return $lines;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function periodBounds(OperationReportFilters $filters): array
    {
        return [$filters->from->startOfDay()->toDateTimeString(), $filters->to->endOfDay()->toDateTimeString()];
    }

    private static function days(OperationReportFilters $filters): int
    {
        return max(1, (int) $filters->from->startOfDay()->diffInDays($filters->to->startOfDay()) + 1);
    }

    private static function stockStatus(int $total, int $threshold): string
    {
        return match (true) {
            $total <= 0 => OperationReportFilters::STOCK_OUT,
            $total <= $threshold => OperationReportFilters::STOCK_LOW,
            default => OperationReportFilters::STOCK_AVAILABLE,
        };
    }

    private static function isAdjustment(string $reason): bool
    {
        return str_starts_with(mb_strtoupper(trim($reason)), 'AJUSTE');
    }

    /**
     * @template T
     *
     * @param  list<T>  $rows
     * @return list<T>
     */
    private static function limit(array $rows, ?int $rowLimit): array
    {
        return $rowLimit === null ? $rows : array_slice($rows, 0, $rowLimit);
    }

    private static function dateTime(mixed $value): ?string
    {
        return blank($value) ? null : CarbonImmutable::parse((string) $value)->format('d/m/Y h:i a');
    }

    private static function number(float $value): int|float
    {
        return floor($value) === $value ? (int) $value : round($value, 2);
    }

    private static function format(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }
}
