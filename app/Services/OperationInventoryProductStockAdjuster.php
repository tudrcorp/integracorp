<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OperationInventory;
use App\Models\OperationInventoryEntry;
use App\Models\OperationInventoryOutflow;
use App\Models\OperationInventoryProduct;
use App\Models\OperationInventoryProductStock;
use App\Models\OperationInventoryType;
use App\Models\OperationInventoryUbication;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class OperationInventoryProductStockAdjuster
{
    public const TYPE_REPOSITION = 'REPOSICIÓN DE INVENTARIO';

    public const TYPE_ADJUSTMENT = 'AJUSTE DE INVENTARIO';

    /**
     * @param  Collection<int, OperationInventoryProduct>  $products
     * @return array{updated: int, quantity: int, ubication: string}
     */
    public function increase(Collection $products, int $ubicationId, int $quantity): array
    {
        return $this->adjust($products, $ubicationId, $quantity, increase: true);
    }

    /**
     * @param  Collection<int, OperationInventoryProduct>  $products
     * @return array{updated: int, quantity: int, ubication: string}
     */
    public function decrease(Collection $products, int $ubicationId, int $quantity, string $note): array
    {
        $note = trim($note);

        if ($note === '') {
            throw new InvalidArgumentException('Debe indicar el motivo del ajuste de existencia.');
        }

        return $this->adjust($products, $ubicationId, $quantity, increase: false, note: $note);
    }

    /**
     * @param  Collection<int, OperationInventoryProduct>  $products
     * @return array{updated: int, quantity: int, ubication: string}
     */
    private function adjust(
        Collection $products,
        int $ubicationId,
        int $quantity,
        bool $increase,
        ?string $note = null,
    ): array {
        $quantity = max(1, $quantity);

        if ($products->isEmpty()) {
            throw new InvalidArgumentException('Debe seleccionar al menos un producto.');
        }

        $ubication = OperationInventoryUbication::query()->find($ubicationId);

        if ($ubication === null) {
            throw new InvalidArgumentException('El almacén seleccionado no existe.');
        }

        $userName = Auth::user()?->name ?? 'system';
        $typeId = (int) (OperationInventoryType::query()->orderBy('id')->value('id') ?? 1);
        $updated = 0;
        /** @var list<array{product_id: int, previous_total: int}> $crossedCandidates */
        $crossedCandidates = [];

        DB::transaction(function () use (
            $products,
            $ubication,
            $quantity,
            $increase,
            $note,
            $userName,
            $typeId,
            &$updated,
            &$crossedCandidates,
        ): void {
            foreach ($products as $product) {
                if (! $product instanceof OperationInventoryProduct) {
                    continue;
                }

                $previousTotal = $increase ? null : $product->totalExistence();

                $inventory = $this->resolveInventory($product, $ubication, $typeId, $userName);
                $stock = $this->resolveStock($product, $ubication, $userName);

                $inventory = OperationInventory::query()
                    ->whereKey($inventory->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $stock = OperationInventoryProductStock::query()
                    ->whereKey($stock->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($increase) {
                    $inventory->existence = (int) $inventory->existence + $quantity;
                    $stock->existence = (int) $stock->existence + $quantity;

                    OperationInventoryEntry::query()->create([
                        'operation_inventory_id' => $inventory->id,
                        'operation_inventory_product_id' => $product->id,
                        'operation_inventory_ubication_id' => $ubication->id,
                        'operation_inventory_type_id' => $typeId,
                        'quantity' => $quantity,
                        'type_entry' => self::TYPE_REPOSITION,
                        'created_by' => $userName,
                    ]);
                } else {
                    $available = min((int) $inventory->existence, (int) $stock->existence);

                    if ($available < $quantity) {
                        throw new RuntimeException(
                            "Existencia insuficiente para «{$product->name}» en {$ubication->name}. Disponible: {$available}."
                        );
                    }

                    $inventory->existence = (int) $inventory->existence - $quantity;
                    $stock->existence = (int) $stock->existence - $quantity;

                    OperationInventoryOutflow::query()->create([
                        'operation_inventory_id' => $inventory->id,
                        'operation_inventory_product_id' => $product->id,
                        'operation_inventory_ubication_id' => $ubication->id,
                        'operation_inventory_type_id' => $typeId,
                        'quantity' => $quantity,
                        'type_entry' => self::TYPE_ADJUSTMENT,
                        'observations' => $note,
                        'created_by' => $userName,
                    ]);

                    $crossedCandidates[] = [
                        'product_id' => (int) $product->id,
                        'previous_total' => (int) $previousTotal,
                    ];
                }

                $inventory->updated_by = $userName;
                $inventory->save();

                $stock->updated_by = $userName;
                $stock->save();

                $updated++;
            }
        });

        if ($crossedCandidates !== []) {
            $watcher = app(OperationInventoryLowStockWatcher::class);

            foreach ($crossedCandidates as $candidate) {
                $watcher->dispatchIfCrossedThreshold(
                    $candidate['product_id'],
                    $candidate['previous_total'],
                );
            }
        }

        return [
            'updated' => $updated,
            'quantity' => $quantity,
            'ubication' => (string) $ubication->name,
        ];
    }

    private function resolveInventory(
        OperationInventoryProduct $product,
        OperationInventoryUbication $ubication,
        int $typeId,
        string $userName,
    ): OperationInventory {
        return OperationInventory::query()->firstOrCreate(
            [
                'operation_inventory_product_id' => $product->id,
                'operation_inventory_ubication_id' => $ubication->id,
            ],
            [
                'name' => $product->name,
                'unit' => $product->unit,
                'operation_inventory_type_id' => $typeId,
                'existence' => 0,
                'cost' => $product->cost,
                'ubication' => $ubication->name,
                'barcode' => $product->code,
                'min_stock' => 0,
                'is_active' => true,
                'created_by' => $userName,
                'updated_by' => $userName,
            ],
        );
    }

    private function resolveStock(
        OperationInventoryProduct $product,
        OperationInventoryUbication $ubication,
        string $userName,
    ): OperationInventoryProductStock {
        $stock = OperationInventoryProductStock::query()->firstOrNew([
            'operation_inventory_product_id' => $product->id,
            'operation_inventory_ubication_id' => $ubication->id,
        ]);

        if (! $stock->exists) {
            $stock->existence = 0;
            $stock->created_by = $userName;
        }

        $stock->updated_by = $userName;
        $stock->save();

        return $stock;
    }
}
