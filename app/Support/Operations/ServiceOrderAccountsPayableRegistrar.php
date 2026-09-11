<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Enums\StatusCuentaPorPagar;
use App\Models\OperationAccountsPayable;
use App\Models\OperationServiceOrder;

/**
 * Crea o actualiza la cuenta por pagar que corresponde a la factura de una orden
 * de servicio.
 *
 * La operación es idempotente: una orden tiene como mucho una cuenta por pagar,
 * identificada por `operation_service_order_id`. Al actualizar la factura se
 * refrescan los datos del documento, pero **nunca** se tocan los datos del pago
 * ya registrado (estatus, referencia, fecha, bancos y montos).
 */
final class ServiceOrderAccountsPayableRegistrar
{
    /**
     * @param  array{
     *     invoice_number: string,
     *     invoice_control_number: ?string,
     *     invoice_date: mixed,
     *     invoice_registration_date: mixed,
     *     invoice_amount_usd: ?float,
     *     invoice_amount_ves: ?float,
     *     invoice_file_path: ?string,
     *     supplier_name: string,
     *     supplier_rif: string,
     *     business_unit_id: ?int,
     * }  $data
     * @return array{payable: OperationAccountsPayable, created: bool, payment_preserved: bool}
     */
    public static function register(OperationServiceOrder $order, array $data, string $actor): array
    {
        $payable = OperationAccountsPayable::query()
            ->where('operation_service_order_id', $order->getKey())
            ->first();

        $created = $payable === null;

        /*
         * Si la factura ya tenía un pago registrado se conserva tal cual: corregir
         * el documento no puede borrar una referencia bancaria ya emitida.
         */
        $paymentPreserved = ! $created && $payable->payment_status !== StatusCuentaPorPagar::PendientePorPagar;

        [$amount, $currency] = self::resolveAmount($data);

        $attributes = [
            'operation_service_order_id' => $order->getKey(),
            'invoice_date' => $data['invoice_date'],
            'invoice_registration_date' => $data['invoice_registration_date'],
            'invoice_number' => $data['invoice_number'],
            'invoice_control_number' => $data['invoice_control_number'] ?: null,
            'supplier_id' => $order->supplier_id,
            'supplier_name' => $data['supplier_name'],
            'supplier_rif' => $data['supplier_rif'],
            'business_unit_id' => $data['business_unit_id'] ?: null,
            'invoice_amount' => $amount,
            'invoice_currency' => $currency,
            'invoice_file_path' => $data['invoice_file_path'] ?: null,
            'updated_by' => $actor,
        ];

        if ($created) {
            $payable = new OperationAccountsPayable;
            $attributes['payment_status'] = StatusCuentaPorPagar::PendientePorPagar->value;
            $attributes['created_by'] = $actor;
        }

        $payable->fill($attributes)->save();

        return [
            'payable' => $payable,
            'created' => $created,
            'payment_preserved' => $paymentPreserved,
        ];
    }

    /**
     * El monto de la factura se toma en US$ cuando hay un importe real; si sólo
     * hay bolívares —o el importe en US$ viene en cero—, se registra en VES.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: float, 1: string}
     */
    public static function resolveAmount(array $data): array
    {
        $usdRaw = $data['invoice_amount_usd'] ?? null;
        $vesRaw = $data['invoice_amount_ves'] ?? null;

        $usd = ($usdRaw !== null && $usdRaw !== '') ? round((float) $usdRaw, 4) : null;
        $ves = ($vesRaw !== null && $vesRaw !== '') ? round((float) $vesRaw, 4) : null;

        if ($usd !== null && $usd > 0.0) {
            return [$usd, 'USD'];
        }

        // Un US$ 0 con bolívares cargados significa que la factura se emitió en Bs.
        if ($ves !== null && $ves > 0.0) {
            return [$ves, 'VES'];
        }

        return $usd !== null ? [$usd, 'USD'] : [$ves ?? 0.0, 'VES'];
    }

    /**
     * Nombre del proveedor sugerido para la orden, en orden de preferencia.
     */
    public static function suggestedSupplierName(OperationServiceOrder $order): string
    {
        $supplier = $order->supplier;

        if ($supplier !== null) {
            $name = trim((string) ($supplier->name ?: $supplier->razon_social));

            if ($name !== '') {
                return $name;
            }
        }

        return trim((string) $order->supplier_external);
    }

    public static function suggestedSupplierRif(OperationServiceOrder $order): string
    {
        return trim((string) ($order->supplier?->rif ?? ''));
    }

    public static function suggestedBusinessUnitId(OperationServiceOrder $order): ?int
    {
        $unitId = $order->operationCoordinationService?->business_unit_id;

        return $unitId !== null ? (int) $unitId : null;
    }
}
