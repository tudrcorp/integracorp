<?php

declare(strict_types=1);

namespace App\Support\ApiPartners;

use App\Models\FarmadocOrder;
use App\Support\Farmadoc\FarmadocReceiptNote;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Pedidos de medicamentos a Farmadoc tal como los ve Farmadoc en el API de aliados:
 * su estatus en IntegraCorp (incluido «registrado-inventario») y la nota de recibido.
 * Sin datos del paciente ni montos.
 */
final class FarmadocOrderFeed
{
    public const DEFAULT_PER_PAGE = 25;

    public const MAX_PER_PAGE = 100;

    public const ORDER_NUMBER_PATTERN = '[A-Za-z0-9][A-Za-z0-9._-]{0,99}';

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return array_keys(FarmadocOrder::statusOptions());
    }

    /**
     * @param  array{estatus?: list<string>, actualizado_desde?: string, por_pagina?: int|string, pagina?: int|string}  $filters
     * @return LengthAwarePaginator<int, FarmadocOrder>
     */
    public static function paginate(array $filters): LengthAwarePaginator
    {
        $query = FarmadocOrder::query()->with('receiptUbication:id,name');

        if (($filters['estatus'] ?? []) !== []) {
            $query->whereIn('status', $filters['estatus']);
        }

        if (filled($filters['actualizado_desde'] ?? null)) {
            $query->where('updated_at', '>=', CarbonImmutable::parse($filters['actualizado_desde']))
                ->orderBy('updated_at')
                ->orderBy('id');
        } else {
            $query->orderByDesc('id');
        }

        $perPage = max(1, min(self::MAX_PER_PAGE, (int) ($filters['por_pagina'] ?? self::DEFAULT_PER_PAGE)));

        return $query->paginate(perPage: $perPage, pageName: 'pagina', page: max(1, (int) ($filters['pagina'] ?? 1)));
    }

    public static function findByNumber(string $orderNumber): ?FarmadocOrder
    {
        return FarmadocOrder::query()->with('receiptUbication:id,name')->where('order_number', $orderNumber)->first();
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(FarmadocOrder $order, string $receiptNoteUrl): array
    {
        $received = FarmadocReceiptNote::available($order);

        return [
            'numero' => (string) $order->order_number,
            'pedido_farmadoc_id' => $order->farmadoc_order_id,
            'orden_servicio' => $order->service_order_number,
            'estatus' => (string) $order->status,
            'estatus_etiqueta' => FarmadocOrder::statusLabel($order->status),
            'nota_entrega' => $order->delivery_note_number,
            'aceptado_en' => $order->accepted_at?->toIso8601String(),
            'recepcion' => $received ? [
                'nota_recibido' => (string) $order->receipt_number,
                'nota_recibido_url' => $receiptNoteUrl,
                'registrado_en' => $order->received_at?->toIso8601String(),
                'recibido_por' => $order->received_by_name,
                'ubicacion' => $order->receiptUbication?->name,
                'completo' => self::isComplete($order),
                'observaciones' => $order->receipt_notes,
                'medicamentos' => self::receiptLines($order),
            ] : null,
            'enviado_en' => $order->created_at?->toIso8601String(),
            'actualizado_en' => $order->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{nombre: string, codigo_barras: string|null, sku: string|null, cantidad_pedida: int, cantidad_recibida: int}>
     */
    private static function receiptLines(FarmadocOrder $order): array
    {
        $lines = [];

        foreach (is_array($order->receipt_items) ? $order->receipt_items : [] as $line) {
            if (! is_array($line)) {
                continue;
            }

            $lines[] = [
                'nombre' => (string) ($line['name'] ?? ''),
                'codigo_barras' => is_string($line['barcode'] ?? null) ? $line['barcode'] : null,
                'sku' => is_string($line['sku'] ?? null) ? $line['sku'] : null,
                'cantidad_pedida' => (int) ($line['quantity_ordered'] ?? 0),
                'cantidad_recibida' => (int) ($line['quantity_received'] ?? 0),
            ];
        }

        return $lines;
    }

    private static function isComplete(FarmadocOrder $order): bool
    {
        foreach (self::receiptLines($order) as $line) {
            if ($line['cantidad_recibida'] < $line['cantidad_pedida']) {
                return false;
            }
        }

        return true;
    }
}
