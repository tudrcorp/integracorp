<?php

declare(strict_types=1);

namespace App\Support\Farmadoc;

use App\Models\FarmadocOrder;

/**
 * Textos de la entrada a inventario de un pedido Farmadoc, para la ficha y el API.
 */
final class FarmadocReceiptLabels
{
    public static function action(?string $action): string
    {
        return match ($action) {
            FarmadocInventoryReceipt::ACTION_EXACT => 'Sumado (coincidía por código)',
            FarmadocInventoryReceipt::ACTION_UPDATE => 'Producto actualizado y sumado',
            FarmadocInventoryReceipt::ACTION_CREATE => 'Producto nuevo',
            FarmadocInventoryReceipt::ACTION_SKIPPED => 'No llegó',
            default => '—',
        };
    }

    public static function actionColor(?string $action): string
    {
        return match ($action) {
            FarmadocInventoryReceipt::ACTION_EXACT => 'success',
            FarmadocInventoryReceipt::ACTION_UPDATE => 'info',
            FarmadocInventoryReceipt::ACTION_CREATE => 'primary',
            FarmadocInventoryReceipt::ACTION_SKIPPED => 'warning',
            default => 'gray',
        };
    }

    /**
     * @return list<array{name: string, received_label: string, action: string|null, product_name: string|null}>
     */
    public static function lines(FarmadocOrder $order): array
    {
        $lines = [];

        foreach (is_array($order->receipt_items) ? $order->receipt_items : [] as $line) {
            if (! is_array($line)) {
                continue;
            }

            $lines[] = [
                'name' => (string) ($line['name'] ?? '—'),
                'received_label' => (int) ($line['quantity_received'] ?? 0).' de '.(int) ($line['quantity_ordered'] ?? 0),
                'action' => is_string($line['action'] ?? null) ? $line['action'] : null,
                'product_name' => is_string($line['product_name'] ?? null) ? $line['product_name'] : null,
            ];
        }

        return $lines;
    }
}
