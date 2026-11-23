<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Enums\StatusCuentaPorPagar;
use App\Models\OperationAccountsPayable;
use App\Models\OperationCashPayment;
use App\Models\OperationServiceOrder;
use App\Models\User;
use App\Support\Operations\CashPayments\CashPaymentService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Una factura de proveedor puede cubrir varias órdenes, siempre que todas
 * pertenezcan al mismo proveedor. Cada orden conserva su propio monto (prorrateo
 * del total de la factura) y su cuenta por pagar, para no duplicar el compromiso.
 *
 * Si la factura se pagó de contado, el pago cubre el total y se reparte entre las
 * cuentas por pagar con la misma proporción; todas sus partes se reversan juntas.
 */
final class ServiceOrderBulkInvoice
{
    /**
     * Identidad estable del proveedor de la orden.
     *
     * `supplier_id` y `telemedicine_supplier_id` apuntan al mismo catálogo, así
     * que se tratan como el mismo proveedor cuando el id coincide.
     */
    public static function supplierKey(OperationServiceOrder $record): ?string
    {
        $supplierId = filled($record->supplier_id)
            ? (int) $record->supplier_id
            : (filled($record->telemedicine_supplier_id) ? (int) $record->telemedicine_supplier_id : null);

        if ($supplierId !== null && $supplierId > 0) {
            return 'supplier:'.$supplierId;
        }

        if (filled($record->doctor_nurse_id)) {
            return 'doctor_nurse:'.(int) $record->doctor_nurse_id;
        }

        $normalizedExternal = preg_replace('/\s+/', ' ', trim((string) $record->supplier_external));
        $external = mb_strtoupper(is_string($normalizedExternal) ? $normalizedExternal : '');

        if ($external !== '') {
            return 'external:'.$external;
        }

        return null;
    }

    public static function supplierDisplayName(OperationServiceOrder $record): string
    {
        if ($record->relationLoaded('supplier') && filled($record->supplier?->name)) {
            return (string) $record->supplier->name;
        }

        if (filled($record->supplier_external)) {
            return (string) $record->supplier_external;
        }

        if ($record->relationLoaded('telemedicineSupplier') && filled($record->telemedicineSupplier?->name)) {
            return (string) $record->telemedicineSupplier->name;
        }

        if ($record->relationLoaded('doctorNurse') && filled($record->doctorNurse?->name)) {
            return (string) $record->doctorNurse->name;
        }

        return '—';
    }

    /**
     * @param  Collection<int, OperationServiceOrder>  $records
     */
    public static function haveSameSupplier(Collection $records): bool
    {
        if ($records->count() < 1) {
            return false;
        }

        $keys = $records
            ->map(fn (OperationServiceOrder $record): ?string => self::supplierKey($record))
            ->unique()
            ->values();

        return $keys->count() === 1 && $keys->first() !== null;
    }

    /**
     * @param  Collection<int, OperationServiceOrder>  $records
     */
    public static function mismatchMessage(Collection $records): string
    {
        if ($records->isEmpty()) {
            return 'Selecciona al menos una orden de servicio.';
        }

        $withoutProvider = $records
            ->filter(fn (OperationServiceOrder $record): bool => self::supplierKey($record) === null)
            ->map(fn (OperationServiceOrder $record): string => (string) ($record->order_number ?: '#'.$record->getKey()))
            ->filter()
            ->values();

        if ($withoutProvider->isNotEmpty()) {
            return 'Todas las órdenes deben tener un proveedor. Falta en: '.$withoutProvider->implode(', ').'.';
        }

        $names = $records
            ->map(fn (OperationServiceOrder $record): string => self::supplierDisplayName($record))
            ->unique()
            ->values();

        return 'Para cargar una sola factura debes seleccionar órdenes del mismo proveedor. Ahora hay '.$names->count()
            .' distintos: '.$names->implode(', ').'.';
    }

    /**
     * @param  Collection<int, OperationServiceOrder>  $records
     */
    public static function serviceCostTotalUsd(Collection $records): float
    {
        $total = 0.0;

        foreach ($records as $record) {
            $total += OperationServiceOrderListDisplay::serviceCostUsd($record) ?? 0.0;
        }

        return round($total, 4);
    }

    /**
     * Reparte el total de la factura entre las órdenes según el peso del costo del servicio (sin ganancia).
     *
     * @param  Collection<int, OperationServiceOrder>  $records
     * @return array<int, array{usd: ?float, ves: ?float}>
     */
    public static function allocateAmounts(Collection $records, ?float $invoiceUsd, ?float $invoiceVes): array
    {
        if ($invoiceUsd === null && $invoiceVes === null) {
            throw new InvalidArgumentException('Indica al menos el monto facturado en US$ o en bolívares.');
        }

        $usdShares = $invoiceUsd !== null ? self::shareByWeight($records, $invoiceUsd) : [];
        $vesShares = $invoiceVes !== null ? self::shareByWeight($records, $invoiceVes) : [];

        $allocated = [];

        foreach ($records as $record) {
            $id = (int) $record->getKey();
            $allocated[$id] = [
                'usd' => $invoiceUsd !== null ? ($usdShares[$id] ?? 0.0) : null,
                'ves' => $invoiceVes !== null ? ($vesShares[$id] ?? 0.0) : null,
            ];
        }

        return $allocated;
    }

    /**
     * @param  Collection<int, OperationServiceOrder>  $records
     * @param  array<string, mixed>  $data
     * @return array{updated: int, payables_created: int, payments_preserved: int, quote_difference_usd: ?float, cash_payments: int, cash_reference: ?string}
     */
    public static function apply(Collection $records, array $data, string $actor, ?User $user = null): array
    {
        if (! self::haveSameSupplier($records)) {
            throw new InvalidArgumentException(self::mismatchMessage($records));
        }

        $filePath = self::normalizePath($data['invoice_file_path'] ?? null);

        if ($filePath === null) {
            throw new InvalidArgumentException('Adjunta el archivo de la factura para poder registrarla.');
        }

        $invoiceNumber = trim((string) ($data['invoice_number'] ?? ''));

        if ($invoiceNumber === '') {
            throw new InvalidArgumentException('Indica el número de la factura.');
        }

        $usdRaw = $data['invoice_amount_usd'] ?? null;
        $vesRaw = $data['invoice_amount_ves'] ?? null;
        $usd = ($usdRaw !== null && $usdRaw !== '') ? round((float) $usdRaw, 4) : null;
        $ves = ($vesRaw !== null && $vesRaw !== '') ? round((float) $vesRaw, 4) : null;

        $allocated = self::allocateAmounts($records, $usd, $ves);
        $controlNumber = trim((string) ($data['invoice_control_number'] ?? ''));
        $registrationDate = $data['invoice_registration_date'] ?: now()->toDateString();
        $supplierName = trim((string) ($data['payable_supplier_name'] ?? ''));
        $supplierRif = trim((string) ($data['payable_supplier_rif'] ?? ''));
        $formBusinessUnitId = filled($data['payable_business_unit_id'] ?? null)
            ? (int) $data['payable_business_unit_id']
            : null;

        if ($supplierName === '' || $supplierRif === '') {
            throw new InvalidArgumentException('Indica el nombre y el RIF del proveedor que emite la factura.');
        }

        $serviceCostTotal = self::serviceCostTotalUsd($records);
        $quoteDifference = ($usd !== null && $serviceCostTotal > 0)
            ? round($usd - $serviceCostTotal, 2)
            : null;

        /** Pago de contado: se valida contra el total de la factura antes de escribir nada. */
        $cashPayment = null;
        [$invoiceTotal, $invoiceCurrency] = ServiceOrderAccountsPayableRegistrar::resolveAmount(['invoice_amount_usd' => $usd, 'invoice_amount_ves' => $ves]);

        if ((bool) ($data['cash_paid'] ?? false)) {
            $blockers = self::cashPaymentBlockers($records);

            if ($blockers !== []) {
                throw new InvalidArgumentException(self::cashPaymentBlockerMessage($blockers));
            }

            $cashPayment = CashPaymentService::normalize(CashPaymentService::paymentData($data), (float) $invoiceTotal, (string) $invoiceCurrency, $data['invoice_date'] ?? null);
        }

        return DB::transaction(function () use (
            $records,
            $allocated,
            $invoiceNumber,
            $controlNumber,
            $data,
            $registrationDate,
            $filePath,
            $supplierName,
            $supplierRif,
            $formBusinessUnitId,
            $actor,
            $quoteDifference,
            $cashPayment,
            $invoiceCurrency,
            $user,
        ): array {
            $ids = $records
                ->map(fn (OperationServiceOrder $record): int => (int) $record->getKey())
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();

            $locked = OperationServiceOrder::query()
                ->with([
                    'supplier',
                    'telemedicineSupplier',
                    'approvedOperationQuote',
                    'operationCoordinationService',
                ])
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $updated = 0;
            $created = 0;
            $preserved = 0;
            /** @var array<int, array{order: OperationServiceOrder, payable: OperationAccountsPayable}> $payables */
            $payables = [];

            foreach ($ids as $id) {
                /** @var OperationServiceOrder|null $order */
                $order = $locked->get($id);

                if ($order === null) {
                    continue;
                }

                $share = $allocated[$id] ?? ['usd' => null, 'ves' => null];
                $businessUnitId = ServiceOrderAccountsPayableRegistrar::suggestedBusinessUnitId($order)
                    ?: $formBusinessUnitId;

                $order->update([
                    'invoice_number' => $invoiceNumber,
                    'invoice_control_number' => $controlNumber !== '' ? $controlNumber : null,
                    'invoice_date' => $data['invoice_date'],
                    'invoice_registration_date' => $registrationDate,
                    'invoice_amount_usd' => $share['usd'],
                    'invoice_amount_ves' => $share['ves'],
                    'invoice_file_path' => $filePath,
                    'invoice_uploaded_by' => $actor,
                    'invoice_uploaded_at' => now(),
                    'administrative_status' => OperationServiceOrderListDisplay::ADMINISTRATIVE_STATUS_INVOICED,
                    'updated_by' => $actor,
                ]);

                $payable = ServiceOrderAccountsPayableRegistrar::register($order, [
                    'invoice_number' => $invoiceNumber,
                    'invoice_control_number' => $controlNumber !== '' ? $controlNumber : null,
                    'invoice_date' => $data['invoice_date'],
                    'invoice_registration_date' => $registrationDate,
                    'invoice_amount_usd' => $share['usd'],
                    'invoice_amount_ves' => $share['ves'],
                    'invoice_file_path' => $filePath,
                    'supplier_name' => $supplierName,
                    'supplier_rif' => $supplierRif,
                    'business_unit_id' => $businessUnitId,
                ], $actor);

                $updated++;
                $payables[$id] = ['order' => $order, 'payable' => $payable['payable']];

                if ($payable['created']) {
                    $created++;
                }

                if ($payable['payment_preserved']) {
                    $preserved++;
                }
            }

            $cashPayments = 0;

            if ($cashPayment !== null && $payables !== []) {
                $shareKey = $invoiceCurrency === 'USD' ? 'usd' : 'ves';
                $shares = [];

                foreach (array_keys($payables) as $id) {
                    $shares[$id] = (float) ($allocated[$id][$shareKey] ?? 0.0);
                }

                $batch = count($payables) > 1 ? (string) Str::uuid() : null;

                /** Si una cuenta ya no está pendiente, register() lanza y se revierte todo: factura y pago. */
                foreach (CashPaymentService::split($cashPayment, $shares) as $id => $part) {
                    CashPaymentService::register($payables[$id]['order'], $payables[$id]['payable'], $part, $user, $actor, $batch);
                    $cashPayments++;
                }
            }

            return [
                'updated' => $updated,
                'payables_created' => $created,
                'payments_preserved' => $preserved,
                'quote_difference_usd' => $quoteDifference,
                'cash_payments' => $cashPayments,
                'cash_reference' => $cashPayment['payment_reference'] ?? null,
            ];
        });
    }

    /**
     * Órdenes que impiden el pago de contado masivo: su cuenta por pagar ya tiene un
     * pago o ya tienen un pago de contado activo.
     *
     * @param  Collection<int, OperationServiceOrder>  $records
     * @return list<string> número de cada orden bloqueada
     */
    public static function cashPaymentBlockers(Collection $records): array
    {
        $ids = $records->map(fn (OperationServiceOrder $record): int => (int) $record->getKey())->filter()->unique()->values()->all();

        if ($ids === []) {
            return [];
        }

        $blocked = OperationAccountsPayable::query()
            ->whereIn('operation_service_order_id', $ids)
            ->get(['operation_service_order_id', 'payment_status'])
            ->filter(fn (OperationAccountsPayable $payable): bool => StatusCuentaPorPagar::fromStored($payable->payment_status) !== StatusCuentaPorPagar::PendientePorPagar)
            ->pluck('operation_service_order_id')
            ->merge(OperationCashPayment::query()
                ->whereIn('operation_service_order_id', $ids)
                ->where('status', OperationCashPayment::STATUS_REGISTERED)
                ->pluck('operation_service_order_id'))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->all();

        return $records
            ->filter(fn (OperationServiceOrder $record): bool => in_array((int) $record->getKey(), $blocked, true))
            ->map(fn (OperationServiceOrder $record): string => (string) ($record->order_number ?: '#'.$record->getKey()))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $blockers
     */
    public static function cashPaymentBlockerMessage(array $blockers): string
    {
        return (count($blockers) === 1 ? 'La orden '.$blockers[0].' ya tiene' : 'Las órdenes '.implode(', ', $blockers).' ya tienen')
            .' un pago registrado, así que no se puede pagar de contado esta factura. Quítalas de la selección o desactiva «Se pagó de contado».';
    }

    public static function normalizePath(mixed $path): ?string
    {
        if (is_array($path)) {
            $path = reset($path) ?: null;
        }

        if (! is_string($path)) {
            return null;
        }

        $path = trim($path);

        return $path !== '' ? $path : null;
    }

    public static function money(?float $amount, string $currency = 'USD'): string
    {
        if ($amount === null) {
            return '—';
        }

        $symbol = $currency === 'VES' ? 'Bs. ' : 'US$ ';

        return $symbol.number_format($amount, 2, ',', '.');
    }

    /**
     * Tabla compacta de las órdenes elegidas más la sumatoria, para el modal masivo.
     *
     * @param  Collection<int, OperationServiceOrder>  $records
     */
    public static function renderSelectedTable(Collection $records): HtmlString
    {
        $frame = 'border-radius:14px;overflow:hidden;border:1px solid rgba(120,130,150,0.28);'
            .'background:rgba(120,130,150,0.08);';
        $th = 'padding:0.55rem 0.7rem;text-align:left;font-size:0.7rem;font-weight:700;'
            .'letter-spacing:0.04em;text-transform:uppercase;opacity:0.75;white-space:nowrap;';
        $td = 'padding:0.55rem 0.7rem;border-top:1px solid rgba(120,130,150,0.22);font-size:0.8125rem;vertical-align:top;';

        $rows = '';

        foreach ($records as $record) {
            $already = OperationServiceOrderListDisplay::hasInvoice($record)
                ? '<div style="margin-top:0.2rem;font-size:0.7rem;opacity:0.75;">Ya facturada'
                    .(filled($record->invoice_number) || filled($record->invoice_receipt_number) ? ' · '.e($record->invoiceDocumentLabel()) : '')
                    .'</div>'
                : '';

            $rows .= '<tr>'
                .'<td style="'.$td.'font-weight:600;">'.e((string) ($record->order_number ?: '—')).$already.'</td>'
                .'<td style="'.$td.'">'.e(OperationServiceOrderListDisplay::patientFullName($record))
                .'<div style="margin-top:0.15rem;font-size:0.7rem;opacity:0.7;">'
                .e(OperationServiceOrderListDisplay::patientDocumentLabel($record))
                .'</div></td>'
                .'<td style="'.$td.'">'.e(self::supplierDisplayName($record)).'</td>'
                .'<td style="'.$td.'">'.e(filled($record->service_type) ? mb_strtoupper((string) $record->service_type) : '—').'</td>'
                .'<td style="'.$td.'text-align:right;font-weight:600;white-space:nowrap;">'
                .e(OperationServiceOrderListDisplay::serviceCostLabel($record))
                .'</td>'
                .'</tr>';
        }

        $count = $records->count();
        $total = self::serviceCostTotalUsd($records);
        $foot = 'padding:0.65rem 0.7rem;border-top:2px solid rgba(120,130,150,0.35);font-weight:700;font-size:0.8125rem;';

        return new HtmlString(
            '<div style="'.$frame.'">'
            .'<table style="width:100%;border-collapse:collapse;">'
            .'<thead><tr>'
            .'<th style="'.$th.'">Orden</th>'
            .'<th style="'.$th.'">Paciente</th>'
            .'<th style="'.$th.'">Proveedor</th>'
            .'<th style="'.$th.'">Tipo</th>'
            .'<th style="'.$th.'text-align:right;">Costo del servicio</th>'
            .'</tr></thead>'
            .'<tbody>'.$rows.'</tbody>'
            .'<tfoot><tr>'
            .'<td style="'.$foot.'" colspan="4">'.$count.' '.($count === 1 ? 'orden seleccionada' : 'órdenes seleccionadas').'</td>'
            .'<td style="'.$foot.'text-align:right;white-space:nowrap;">Total '.e(self::money($total)).'</td>'
            .'</tr></tfoot>'
            .'</table></div>'
        );
    }

    /**
     * @param  Collection<int, OperationServiceOrder>  $records
     * @return array<int, float>
     */
    private static function shareByWeight(Collection $records, float $total): array
    {
        $weights = [];

        foreach ($records as $record) {
            $quote = OperationServiceOrderListDisplay::serviceCostUsd($record);
            $weights[(int) $record->getKey()] = ($quote !== null && $quote > 0) ? $quote : 0.0;
        }

        $weightSum = array_sum($weights);
        $ids = array_keys($weights);
        $lastId = (int) end($ids);
        $shares = [];
        $assigned = 0.0;

        if ($weightSum <= 0.0) {
            $count = max(count($weights), 1);
            $equal = round($total / $count, 4);

            foreach ($ids as $id) {
                if ((int) $id === $lastId) {
                    $shares[(int) $id] = round($total - $assigned, 4);
                } else {
                    $shares[(int) $id] = $equal;
                    $assigned += $equal;
                }
            }

            return $shares;
        }

        foreach ($ids as $id) {
            if ((int) $id === $lastId) {
                $shares[(int) $id] = round($total - $assigned, 4);

                continue;
            }

            $share = round($total * ($weights[(int) $id] / $weightSum), 4);
            $shares[(int) $id] = $share;
            $assigned += $share;
        }

        return $shares;
    }
}
