<?php

declare(strict_types=1);

namespace App\Support\Farmadoc;

use App\Models\FarmadocOrder;
use App\Models\User;
use App\Services\Farmadoc\FarmadocAllyClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Guarda los pedidos enviados a Farmadoc y les actualiza el estatus desde el API.
 * Nunca guarda precios ni montos.
 */
final class FarmadocOrderTracker
{
    /**
     * @param  array{order_number: string, order_id?: int|null, status?: string|null}  $placed
     * @param  list<array{product_id: int, quantity: int, product_name_snapshot?: string|null, sku_snapshot?: string|null}>  $items
     */
    public static function record(
        array $placed,
        array $items,
        ?int $coordinationId,
        ?string $coordinationReference,
        ?string $patientName,
        ?User $user,
    ): FarmadocOrder {
        $lines = array_map(fn (array $item): array => [
            'product_id' => (int) $item['product_id'],
            'name' => (string) ($item['product_name_snapshot'] ?? ''),
            'sku' => is_string($item['sku_snapshot'] ?? null) ? $item['sku_snapshot'] : null,
            'quantity' => (int) $item['quantity'],
        ], $items);

        return FarmadocOrder::query()->firstOrCreate(
            ['order_number' => $placed['order_number']],
            [
                'farmadoc_order_id' => $placed['order_id'] ?? null,
                'operation_coordination_service_id' => $coordinationId,
                'coordination_reference' => self::limit($coordinationReference, 100),
                'patient_name' => self::limit($patientName, 255),
                'items' => $lines,
                'items_count' => count($lines),
                'units_count' => array_sum(array_column($lines, 'quantity')),
                'status' => FarmadocOrder::normalizeStatus($placed['status'] ?? null),
                'status_checked_at' => now(),
                'created_by_user_id' => $user?->getKey(),
                'created_by_name' => self::limit($user?->name, 255),
            ],
        );
    }

    /**
     * @throws FarmadocException
     */
    public static function refresh(FarmadocOrder $order, ?FarmadocAllyClient $client = null): FarmadocOrder
    {
        $client ??= app(FarmadocAllyClient::class);

        try {
            $remote = $client->orderStatus($order->order_number);
        } catch (FarmadocException $exception) {
            $order->forceFill([
                'status_checked_at' => now(),
                'status_check_error' => self::limit($exception->getMessage(), 255),
            ])->save();

            throw $exception;
        }

        $order->forceFill([
            'status' => FarmadocOrder::normalizeStatus($remote['status']),
            'farmadoc_order_id' => $remote['order_id'] ?? $order->farmadoc_order_id,
            'farmadoc_updated_at' => self::parseDate($remote['updated_at']),
            'status_checked_at' => now(),
            'status_check_error' => null,
        ])->save();

        return $order;
    }

    private static function parseDate(?string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private static function limit(?string $value, int $length): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : Str::limit($value, $length - 3);
    }
}
