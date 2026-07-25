<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SystemNotificationKey;
use App\Jobs\NotifyOperationInventoryProductLowStockJob;
use App\Models\OperationInventoryProduct;
use App\Models\OperationInventorySetting;
use App\Support\SystemNotificationRecipients;
use Illuminate\Support\Facades\Log;

class OperationInventoryLowStockWatcher
{
    /**
     * Despacha alerta inmediata si el producto cruzó el umbral hacia stock bajo.
     * Solo notifica al pasar de existencia > umbral a existencia <= umbral.
     */
    public function dispatchIfCrossedThreshold(int $productId, ?int $previousTotalExistence = null): bool
    {
        if ($productId < 1) {
            return false;
        }

        if (! SystemNotificationRecipients::isActive(SystemNotificationKey::OperationInventoryLowStock)) {
            return false;
        }

        $product = OperationInventoryProduct::query()
            ->whereKey($productId)
            ->where('is_active', true)
            ->first();

        if ($product === null) {
            return false;
        }

        $threshold = OperationInventorySetting::current()->lowStockThreshold();
        $currentTotal = $product->totalExistence();

        if ($currentTotal > $threshold) {
            return false;
        }

        if ($previousTotalExistence !== null && $previousTotalExistence <= $threshold) {
            return false;
        }

        try {
            NotifyOperationInventoryProductLowStockJob::dispatch($productId);

            return true;
        } catch (\Throwable $exception) {
            Log::error('OperationInventoryLowStockWatcher: no se pudo despachar la alerta inmediata', [
                'product_id' => $productId,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
