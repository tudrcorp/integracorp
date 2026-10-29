<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Enums\StatusCuentaPorPagar;
use App\Models\OperationAccountsPayable;
use App\Models\OperationServiceOrder;
use App\Models\OperationServiceOrderItem;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Reporte de cuentas por pagar por proveedor, con la forma que usa Administración para
 * conciliar con cada proveedor: una hoja por proveedor, bloques por estatus (pagado,
 * en gestión, pendiente) con su subtotal y un total general.
 *
 * Aquí solo vive la forma de los datos (filas, bloques, nombres); el archivo Excel lo
 * escribe {@see \App\Services\AccountsPayableSupplierReportXlsxService}.
 *
 * @phpstan-type ReportRow array{
 *     id: int,
 *     supplier_key: string,
 *     invoice_date: ?CarbonImmutable,
 *     registration_date: ?CarbonImmutable,
 *     supplier: string,
 *     rif: string,
 *     specific_business_unit: string,
 *     service_type: string,
 *     services: string,
 *     order_number: ?string,
 *     invoice_number: string,
 *     control_number: string,
 *     amount: float,
 *     currency: string,
 *     status: StatusCuentaPorPagar
 * }
 * @phpstan-type ReportBlock array{status: StatusCuentaPorPagar, label: string, total_label: string, rows: list<ReportRow>}
 * @phpstan-type ReportSheet array{name: string, supplier: string, blocks: list<ReportBlock>}
 */
final class AccountsPayableSupplierReport
{
    public const EMPTY = '—';

    public const MANUAL_ORDER = 'Carga manual';

    /**
     * Orden de los bloques en la hoja y cómo se nombran (en la fila, en el subtotal y en el archivo).
     *
     * @var array<string, array{label: string, total: string, filename: string}>
     */
    private const BLOCKS = [
        'PAGADA' => ['label' => 'Pagado', 'total' => 'Total Pagado', 'filename' => 'PAGADOS'],
        'EN GESTION' => ['label' => 'En gestión', 'total' => 'Total En gestión', 'filename' => 'EN GESTION'],
        'PENDIENTE POR PAGAR' => ['label' => 'Pendiente por pagar', 'total' => 'Total Pendiente por pagar', 'filename' => 'PDTE'],
    ];

    private const MONTHS = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

    private const SHEET_NAME_MAX = 31;

    private const FILENAME_PART_MAX = 100;

    /**
     * Relaciones mínimas que necesita {@see self::rowFromRecord()}. La orden se carga sin sus
     * scopes globales: una factura cuyo caso se eliminó sigue debiéndose al proveedor.
     *
     * @return array<string, \Closure>
     */
    public static function eagerLoads(): array
    {
        return [
            'operationServiceOrder' => fn ($query) => $query->withoutGlobalScopes()->select([
                'id', 'order_number', 'service_type', 'description', 'operation_coordination_service_id',
            ]),
            'operationServiceOrder.operationServiceOrderItems' => fn ($query) => $query->select([
                'id', 'operation_service_order_id', 'item_name',
            ])->orderBy('id'),
            'operationServiceOrder.operationCoordinationService' => fn ($query) => $query->select([
                'id', 'telemedicine_patient_id',
            ]),
            'operationServiceOrder.operationCoordinationService.telemedicinePatient' => fn ($query) => $query->select([
                'id', 'specific_business_unit',
            ]),
        ];
    }

    /**
     * @return ReportRow
     */
    public static function rowFromRecord(OperationAccountsPayable $record): array
    {
        /** @var OperationServiceOrder|null $order */
        $order = $record->operationServiceOrder;
        $supplier = self::singleLine((string) $record->supplier_name);
        $rif = self::singleLine((string) $record->supplier_rif);

        return [
            'id' => (int) $record->getKey(),
            'supplier_key' => $record->supplier_id !== null
                ? 'id:'.$record->supplier_id
                : 'name:'.mb_strtoupper($supplier.'|'.$rif, 'UTF-8'),
            'invoice_date' => self::date($record->invoice_date),
            'registration_date' => self::date($record->invoice_registration_date),
            'supplier' => $supplier !== '' ? $supplier : self::EMPTY,
            'rif' => $rif !== '' ? $rif : self::EMPTY,
            'specific_business_unit' => self::orFallback(
                $order?->operationCoordinationService?->telemedicinePatient?->specific_business_unit
            ),
            'service_type' => self::orFallback($order !== null ? mb_strtoupper((string) $order->service_type, 'UTF-8') : null),
            'services' => self::specificServices($order),
            'order_number' => filled($order?->order_number) ? (string) $order->order_number : null,
            'invoice_number' => self::orFallback($record->invoice_number),
            'control_number' => self::orFallback($record->invoice_control_number),
            'amount' => round((float) $record->invoice_amount, 2),
            'currency' => mb_strtoupper(trim((string) $record->invoice_currency), 'UTF-8') === 'VES' ? 'VES' : 'USD',
            'status' => StatusCuentaPorPagar::fromStored($record->getRawOriginal('payment_status'))
                ?? StatusCuentaPorPagar::default(),
        ];
    }

    /**
     * Los ítems de la orden como los escribe el analista: la sigla si el ítem la trae entre
     * paréntesis («HEMATOLOGIA COMPLETA (HC)» → «HC»), si no el nombre, unidos con guion.
     */
    public static function specificServices(?OperationServiceOrder $order): string
    {
        if ($order === null) {
            return self::EMPTY;
        }

        $names = [];
        $items = $order->relationLoaded('operationServiceOrderItems')
            ? $order->operationServiceOrderItems
            : $order->operationServiceOrderItems()->get(['id', 'operation_service_order_id', 'item_name']);

        foreach ($items as $item) {
            /** @var OperationServiceOrderItem $item */
            $name = self::shortItemName((string) $item->item_name);

            if ($name !== '' && ! in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        if ($names !== []) {
            return implode('-', $names);
        }

        return self::orFallback($order->description);
    }

    public static function shortItemName(string $name): string
    {
        $name = self::singleLine($name);

        if (preg_match('/\(([^()]+)\)\s*$/u', $name, $matches) === 1 && trim($matches[1]) !== '') {
            return mb_strtoupper(trim($matches[1]), 'UTF-8');
        }

        return mb_strtoupper($name, 'UTF-8');
    }

    /**
     * Una hoja por proveedor (ordenadas por nombre) con sus bloques por estatus.
     *
     * @param  iterable<ReportRow>  $rows
     * @return list<ReportSheet>
     */
    public static function sheets(iterable $rows): array
    {
        $bySupplier = [];

        foreach ($rows as $row) {
            $bySupplier[$row['supplier_key']][] = $row;
        }

        uasort($bySupplier, fn (array $a, array $b): int => strnatcasecmp($a[0]['supplier'], $b[0]['supplier'])
            ?: strcmp($a[0]['supplier_key'], $b[0]['supplier_key']));

        $sheets = [];
        $usedNames = [];

        foreach ($bySupplier as $supplierRows) {
            $sheets[] = [
                'name' => self::uniqueSheetName($supplierRows[0]['supplier'], $usedNames),
                'supplier' => $supplierRows[0]['supplier'],
                'blocks' => self::blocks($supplierRows),
            ];
        }

        return $sheets;
    }

    /**
     * @param  list<ReportRow>  $rows
     * @return list<ReportBlock>
     */
    public static function blocks(array $rows): array
    {
        $blocks = [];

        foreach (self::BLOCKS as $value => $names) {
            $blockRows = array_values(array_filter($rows, fn (array $row): bool => $row['status']->value === $value));

            if ($blockRows === []) {
                continue;
            }

            usort($blockRows, self::compareRows(...));

            $blocks[] = [
                'status' => StatusCuentaPorPagar::from($value),
                'label' => $names['label'],
                'total_label' => $names['total'],
                'rows' => $blockRows,
            ];
        }

        return $blocks;
    }

    public static function statusLabel(StatusCuentaPorPagar $status): string
    {
        return self::BLOCKS[$status->value]['label'];
    }

    /**
     * «PROVEEDOR-PAGADOS O PDTE-LABORATORIOS-27OCT2026.xlsx». Con varios proveedores o
     * servicios dice «VARIOS …»; el tramo de estatus nombra solo los que se exportaron.
     *
     * @param  list<ReportRow>  $rows
     */
    public static function filename(array $rows, DateTimeInterface $date): string
    {
        $suppliers = array_unique(array_column($rows, 'supplier_key'));
        $supplier = count($suppliers) === 1 ? self::filenamePart($rows[0]['supplier']) : '';

        $types = array_values(array_unique(array_column($rows, 'service_type')));
        $type = count($types) === 1 && $types[0] !== self::EMPTY ? self::filenamePart($types[0]) : '';

        $presentStatuses = array_unique(array_map(fn (array $row): string => $row['status']->value, $rows));
        $statuses = [];

        foreach (self::BLOCKS as $value => $names) {
            if (in_array($value, $presentStatuses, true)) {
                $statuses[] = $names['filename'];
            }
        }

        return implode('-', [
            $supplier !== '' ? $supplier : 'VARIOS PROVEEDORES',
            $statuses !== [] ? implode(' O ', $statuses) : 'SIN ESTATUS',
            $type !== '' ? $type : (count($types) > 1 ? 'VARIOS SERVICIOS' : 'SIN TIPO DE SERVICIO'),
            self::compactDate($date),
        ]).'.xlsx';
    }

    public static function compactDate(DateTimeInterface $date): string
    {
        return $date->format('d').self::MONTHS[(int) $date->format('n') - 1].$date->format('Y');
    }

    /**
     * Excel no admite «[ ] : * ? / \» en el nombre de una hoja, ni más de 31 caracteres,
     * ni dos hojas con el mismo nombre (sin distinguir mayúsculas).
     *
     * @param  array<string, true>  $usedNames
     */
    public static function uniqueSheetName(string $supplier, array &$usedNames): string
    {
        $base = trim((string) preg_replace('/[\[\]:*?\/\\\\]+/u', ' ', self::singleLine($supplier)));
        $base = trim((string) preg_replace('/\s+/u', ' ', $base), " '");
        $base = $base !== '' && $base !== self::EMPTY ? $base : 'Proveedor';

        $name = self::cutAtWord($base, self::SHEET_NAME_MAX);

        for ($copy = 2; isset($usedNames[mb_strtolower($name, 'UTF-8')]); $copy++) {
            $suffix = ' ('.$copy.')';
            $name = self::cutAtWord($base, self::SHEET_NAME_MAX - mb_strlen($suffix)).$suffix;
        }

        $usedNames[mb_strtolower($name, 'UTF-8')] = true;

        return $name;
    }

    /**
     * Recorta sin partir una palabra («INSTITUTO MEDICO MENE GRANDE, C.A.» → «INSTITUTO MEDICO
     * MENE GRANDE»), salvo que el corte deje menos de la mitad del espacio.
     */
    private static function cutAtWord(string $value, int $max): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        $cut = mb_substr($value, 0, $max);
        $space = mb_strrpos($cut, ' ');

        if ($space !== false && $space >= intdiv($max, 2) && mb_substr($value, $max, 1) !== ' ') {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, ' ,.-');
    }

    /**
     * Tramo apto para nombre de archivo: sin comas (rompen la descarga en algunos navegadores)
     * ni caracteres que Windows o macOS rechazan.
     */
    private static function filenamePart(string $value): string
    {
        $value = (string) preg_replace('/[\\\\\/:*?"<>|,;\x00-\x1F\x7F]+/u', ' ', self::singleLine($value));
        $value = trim((string) preg_replace('/\s+/u', ' ', $value), ' -');

        return $value === self::EMPTY ? '' : rtrim(mb_substr($value, 0, self::FILENAME_PART_MAX), ' -');
    }

    /**
     * Por número de orden (ORD-0063 antes que ORD-0104); las cargas manuales al final.
     *
     * @param  ReportRow  $a
     * @param  ReportRow  $b
     */
    private static function compareRows(array $a, array $b): int
    {
        if (($a['order_number'] === null) !== ($b['order_number'] === null)) {
            return $a['order_number'] === null ? 1 : -1;
        }

        return strnatcasecmp((string) $a['order_number'], (string) $b['order_number'])
            ?: ($a['invoice_date']?->getTimestamp() ?? PHP_INT_MAX) <=> ($b['invoice_date']?->getTimestamp() ?? PHP_INT_MAX)
            ?: $a['id'] <=> $b['id'];
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        return $value instanceof DateTimeInterface ? CarbonImmutable::instance($value)->startOfDay() : null;
    }

    private static function orFallback(mixed $value): string
    {
        $value = self::singleLine((string) $value);

        return $value !== '' ? $value : self::EMPTY;
    }

    private static function singleLine(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
