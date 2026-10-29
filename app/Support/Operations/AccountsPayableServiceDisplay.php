<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Enums\StatusCuentaPorPagar;
use App\Models\OperationServiceOrder;
use App\Models\OperationServiceOrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Qué servicio se le paga al proveedor en cada cuenta por pagar: el tipo de la
 * orden de servicio (laboratorio, imagenología, medicamento, especialista) y sus
 * ítems («HEMATOLOGIA COMPLETA (HC)», «ENDOCRINOLOGIA»…). También arma los
 * números del encabezado de la tabla.
 */
final class AccountsPayableServiceDisplay
{
    public const ITEMS_IN_ROW = 3;

    /**
     * @var array<string, array{label: string, plural: string, icon: string, color: string}>
     */
    public const TYPES = [
        'LABORATORIOS' => ['label' => 'Laboratorio', 'plural' => 'Laboratorios', 'icon' => 'heroicon-m-beaker', 'color' => 'info'],
        'IMAGENOLOGIA' => ['label' => 'Imagenología', 'plural' => 'Imagenología', 'icon' => 'heroicon-m-photo', 'color' => 'primary'],
        'MEDICAMENTOS' => ['label' => 'Medicamento', 'plural' => 'Medicamentos', 'icon' => 'heroicon-m-eye-dropper', 'color' => 'success'],
        'ESPECIALISTA' => ['label' => 'Consulta con especialista', 'plural' => 'Especialistas', 'icon' => 'heroicon-m-user-circle', 'color' => 'warning'],
    ];

    /**
     * @return array{key: string, label: string, plural: string, icon: string, color: string}
     */
    public static function type(?string $serviceType): array
    {
        $key = mb_strtoupper(trim((string) $serviceType), 'UTF-8');

        if (isset(self::TYPES[$key])) {
            return ['key' => $key] + self::TYPES[$key];
        }

        $label = $key !== '' ? mb_convert_case(mb_strtolower($key, 'UTF-8'), MB_CASE_TITLE, 'UTF-8') : 'Sin tipo de servicio';

        return ['key' => $key, 'label' => $label, 'plural' => $label, 'icon' => 'heroicon-m-question-mark-circle', 'color' => 'gray'];
    }

    /**
     * Ítems de la orden como texto: en medicamentos, con su cantidad.
     *
     * @return list<string>
     */
    public static function items(?OperationServiceOrder $order): array
    {
        if ($order === null) {
            return [];
        }

        $isMedication = self::type($order->service_type)['key'] === 'MEDICAMENTOS';
        $items = $order->relationLoaded('operationServiceOrderItems') ? $order->operationServiceOrderItems : $order->operationServiceOrderItems()->get();

        return $items
            ->map(function (OperationServiceOrderItem $item) use ($isMedication): string {
                $name = trim((string) $item->item_name);
                $quantity = (float) ($item->quantity ?? 0);

                return $isMedication && $quantity > 0
                    ? $name.' ×'.rtrim(rtrim(number_format($quantity, 2, ',', '.'), '0'), ',')
                    : $name;
            })
            ->filter(fn (string $name): bool => $name !== '')
            ->values()
            ->all();
    }

    /**
     * Línea corta para la fila: los primeros ítems y «y N más».
     */
    public static function itemsLine(?OperationServiceOrder $order): ?string
    {
        $items = self::items($order);

        if ($items === []) {
            return $order !== null && filled($order->description) ? (string) $order->description : null;
        }

        $line = implode(' · ', array_slice($items, 0, self::ITEMS_IN_ROW));
        $rest = count($items) - self::ITEMS_IN_ROW;

        return $rest > 0 ? $line.' y '.$rest.' más' : $line;
    }

    /**
     * Lista completa para el tooltip (solo si no cabe en la fila).
     */
    public static function itemsTooltip(?OperationServiceOrder $order): ?string
    {
        $items = self::items($order);

        return count($items) > self::ITEMS_IN_ROW ? implode("\n", $items) : null;
    }

    /**
     * Valores guardados que significan cada estatus (incluye los heredados).
     *
     * @param  list<StatusCuentaPorPagar>  $statuses
     * @return list<string>
     */
    public static function storedStatusValues(array $statuses): array
    {
        $aliases = [
            StatusCuentaPorPagar::Pagada->value => ['PAGADO', 'CANCELADA', 'CANCELADO'],
            StatusCuentaPorPagar::EnGestion->value => ['EN GESTIÓN', 'GESTIONANDO', 'EN PROCESO'],
            StatusCuentaPorPagar::PendientePorPagar->value => ['PENDIENTE', 'POR PAGAR'],
        ];
        $values = [];

        foreach ($statuses as $status) {
            array_push($values, $status->value, ...($aliases[$status->value] ?? []));
        }

        return array_values(array_unique($values));
    }

    /**
     * @param  Builder<\App\Models\OperationAccountsPayable>  $query
     * @param  list<StatusCuentaPorPagar>  $statuses
     */
    public static function whereStatus(Builder $query, array $statuses): void
    {
        $values = self::storedStatusValues($statuses);
        $query->whereRaw('UPPER(TRIM(payment_status)) IN ('.implode(',', array_fill(0, count($values), '?')).')', $values);
    }

    /**
     * Números del encabezado, en pocas consultas agregadas.
     *
     * @return array{
     *     pending: array{count: int, usd: float, ves: float},
     *     paid_month: array{count: int, usd: float, ves: float},
     *     paid_without_receipt: int,
     *     pending_by_type: list<array{key: string, label: string, icon: string, color: string, count: int, usd: float, ves: float}>
     * }
     */
    public static function overview(): array
    {
        $pendingValues = self::storedStatusValues([StatusCuentaPorPagar::PendientePorPagar, StatusCuentaPorPagar::EnGestion]);
        $paidValues = self::storedStatusValues([StatusCuentaPorPagar::Pagada]);
        $placeholders = static fn (array $values): string => implode(',', array_fill(0, count($values), '?'));

        $pending = DB::table('operation_accounts_payables')
            ->whereRaw('UPPER(TRIM(payment_status)) IN ('.$placeholders($pendingValues).')', $pendingValues)
            ->selectRaw("COUNT(*) as c, SUM(CASE WHEN invoice_currency = 'VES' THEN 0 ELSE invoice_amount END) as usd, SUM(CASE WHEN invoice_currency = 'VES' THEN invoice_amount ELSE 0 END) as ves")
            ->first();

        $paidMonth = DB::table('operation_accounts_payables')
            ->whereRaw('UPPER(TRIM(payment_status)) IN ('.$placeholders($paidValues).')', $paidValues)
            ->whereBetween('payment_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->selectRaw('COUNT(*) as c, SUM(payment_amount_usd) as usd, SUM(payment_amount_ves) as ves')
            ->first();

        $withoutReceipt = DB::table('operation_accounts_payables')
            ->whereRaw('UPPER(TRIM(payment_status)) IN ('.$placeholders($paidValues).')', $paidValues)
            ->where(fn ($query) => $query->whereNull('payment_receipt_path')->orWhere('payment_receipt_path', ''))
            ->count();

        $byType = DB::table('operation_accounts_payables as payables')
            ->leftJoin('operation_service_orders as orders', 'orders.id', '=', 'payables.operation_service_order_id')
            ->whereRaw('UPPER(TRIM(payables.payment_status)) IN ('.$placeholders($pendingValues).')', $pendingValues)
            ->groupBy('orders.service_type')
            ->selectRaw("orders.service_type as service_type, COUNT(*) as c, SUM(CASE WHEN payables.invoice_currency = 'VES' THEN 0 ELSE payables.invoice_amount END) as usd, SUM(CASE WHEN payables.invoice_currency = 'VES' THEN payables.invoice_amount ELSE 0 END) as ves")
            ->get()
            ->keyBy(fn (object $row): string => self::type($row->service_type)['key']);

        $pendingByType = [];

        foreach (array_keys(self::TYPES) as $key) {
            $row = $byType->get($key);
            $type = self::type($key);
            $pendingByType[] = [
                'key' => $key,
                'label' => $type['plural'],
                'icon' => $type['icon'],
                'color' => $type['color'],
                'count' => (int) ($row->c ?? 0),
                'usd' => round((float) ($row->usd ?? 0), 2),
                'ves' => round((float) ($row->ves ?? 0), 2),
            ];
        }

        return [
            'pending' => ['count' => (int) ($pending->c ?? 0), 'usd' => round((float) ($pending->usd ?? 0), 2), 'ves' => round((float) ($pending->ves ?? 0), 2)],
            'paid_month' => ['count' => (int) ($paidMonth->c ?? 0), 'usd' => round((float) ($paidMonth->usd ?? 0), 2), 'ves' => round((float) ($paidMonth->ves ?? 0), 2)],
            'paid_without_receipt' => $withoutReceipt,
            'pending_by_type' => $pendingByType,
        ];
    }

    /**
     * Opciones del filtro por tipo de servicio.
     *
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return array_map(static fn (array $type): string => $type['plural'], self::TYPES);
    }
}
