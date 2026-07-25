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
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class OperationInventoryStockCsvImporter
{
    public const WAREHOUSE_ONE_NAME = 'DIAGNOMOVIL';

    public const WAREHOUSE_TWO_NAME = '3 DE FEBRERO';

    /**
     * @return array{
     *     inventories: int,
     *     entries: int,
     *     outflows: int,
     *     stocks: int,
     *     skipped: int,
     *     missing_codes: list<string>
     * }
     */
    public function importFromPath(string $path, bool $truncate = true): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("No se puede leer el CSV: {$path}");
        }

        $warehouseOne = OperationInventoryUbication::query()
            ->where('name', self::WAREHOUSE_ONE_NAME)
            ->first();

        $warehouseTwo = OperationInventoryUbication::query()
            ->where('name', self::WAREHOUSE_TWO_NAME)
            ->first();

        if ($warehouseOne === null || $warehouseTwo === null) {
            throw new RuntimeException('Deben existir los almacenes DIAGNOMOVIL y 3 DE FEBRERO.');
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("No se pudo abrir el CSV: {$path}");
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false) {
                throw new RuntimeException('El CSV está vacío.');
            }

            $header = array_map(
                fn (mixed $column): string => mb_strtoupper(trim((string) $column)),
                $header
            );

            $codeIndex = $this->columnIndex($header, ['CODIGO', 'CÓDIGO']);
            $warehouseOneIndex = $this->columnIndex($header, ['ALAMACEN 1', 'ALMACEN 1', 'ALMACÉN 1']);
            $warehouseTwoIndex = $this->columnIndex($header, ['ALAMACEN 2', 'ALMACEN 2', 'ALMACÉN 2']);

            return DB::transaction(function () use ($handle, $codeIndex, $warehouseOneIndex, $warehouseTwoIndex, $warehouseOne, $warehouseTwo, $truncate): array {
                if ($truncate) {
                    $this->truncateInventoryTables();
                }

                $inventories = 0;
                $entries = 0;
                $outflows = 0;
                $stocks = 0;
                $skipped = 0;
                /** @var list<string> $missingCodes */
                $missingCodes = [];

                while (($row = fgetcsv($handle)) !== false) {
                    $code = trim((string) ($row[$codeIndex] ?? ''));

                    if ($code === '') {
                        $skipped++;

                        continue;
                    }

                    $product = OperationInventoryProduct::query()->where('code', $code)->first();

                    if ($product === null) {
                        $missingCodes[] = $code;
                        $skipped++;

                        continue;
                    }

                    $qtyOne = (int) trim((string) ($row[$warehouseOneIndex] ?? '0'));
                    $qtyTwo = (int) trim((string) ($row[$warehouseTwoIndex] ?? '0'));

                    foreach ([
                        [$warehouseOne, $qtyOne],
                        [$warehouseTwo, $qtyTwo],
                    ] as [$ubication, $quantity]) {
                        /** @var OperationInventoryUbication $ubication */
                        $result = $this->syncWarehouseStock($product, $ubication, $quantity);
                        $inventories += $result['inventory'] ? 1 : 0;
                        $entries += $result['entry'] ? 1 : 0;
                        $outflows += $result['outflow'] ? 1 : 0;
                        $stocks += $result['stock'] ? 1 : 0;
                    }
                }

                return [
                    'inventories' => $inventories,
                    'entries' => $entries,
                    'outflows' => $outflows,
                    'stocks' => $stocks,
                    'skipped' => $skipped,
                    'missing_codes' => $missingCodes,
                ];
            });
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array{inventory: bool, entry: bool, outflow: bool, stock: bool}
     */
    public function syncWarehouseStock(
        OperationInventoryProduct $product,
        OperationInventoryUbication $ubication,
        int $quantity,
    ): array {
        $existence = max(0, $quantity);
        $negativeQuantity = $quantity < 0 ? abs($quantity) : 0;

        $typeId = $this->defaultInventoryTypeId();

        $inventory = OperationInventory::query()->updateOrCreate(
            [
                'operation_inventory_product_id' => $product->id,
                'operation_inventory_ubication_id' => $ubication->id,
            ],
            [
                'name' => $product->name,
                'unit' => $product->unit,
                'operation_inventory_type_id' => $typeId,
                'existence' => $existence,
                'cost' => $product->cost,
                'ubication' => $ubication->name,
                'barcode' => $product->code,
                'min_stock' => 0,
                'is_active' => true,
                'created_by' => 'INTEGRACORP',
                'updated_by' => 'INTEGRACORP',
            ],
        );

        $entryCreated = false;
        $outflowCreated = false;

        if ($existence > 0) {
            OperationInventoryEntry::query()->create([
                'operation_inventory_id' => $inventory->id,
                'operation_inventory_product_id' => $product->id,
                'operation_inventory_ubication_id' => $ubication->id,
                'operation_inventory_type_id' => $typeId,
                'quantity' => $existence,
                'type_entry' => 'PRIMERA CARGA',
                'created_by' => 'INTEGRACORP',
            ]);
            $entryCreated = true;
        }

        if ($negativeQuantity > 0) {
            OperationInventoryOutflow::query()->create([
                'operation_inventory_id' => $inventory->id,
                'operation_inventory_product_id' => $product->id,
                'operation_inventory_ubication_id' => $ubication->id,
                'operation_inventory_type_id' => $typeId,
                'quantity' => $negativeQuantity,
                'type_entry' => 'AJUSTE INICIAL',
                'created_by' => 'INTEGRACORP',
            ]);
            $outflowCreated = true;
        }

        OperationInventoryProductStock::query()->updateOrCreate(
            [
                'operation_inventory_product_id' => $product->id,
                'operation_inventory_ubication_id' => $ubication->id,
            ],
            [
                'existence' => $existence,
                'created_by' => 'INTEGRACORP',
                'updated_by' => 'INTEGRACORP',
            ],
        );

        return [
            'inventory' => true,
            'entry' => $entryCreated,
            'outflow' => $outflowCreated,
            'stock' => true,
        ];
    }

    private function truncateInventoryTables(): void
    {
        DB::table('operation_inventory_movements')->delete();
        DB::table('operation_inventory_entries')->delete();
        DB::table('operation_inventory_outflows')->delete();
        DB::table('operation_inventory_product_stocks')->delete();
        DB::table('operation_inventories')->delete();
    }

    private function defaultInventoryTypeId(): int
    {
        $typeId = OperationInventoryType::query()->orderBy('id')->value('id');

        if ($typeId === null) {
            throw new RuntimeException('No existe ningún tipo de inventario para asociar las existencias.');
        }

        return (int) $typeId;
    }

    /**
     * @param  list<string>  $header
     * @param  list<string>  $candidates
     */
    private function columnIndex(array $header, array $candidates): int
    {
        foreach ($candidates as $candidate) {
            $index = array_search($candidate, $header, true);

            if ($index !== false) {
                return (int) $index;
            }
        }

        throw new RuntimeException('No se encontró la columna CSV: '.implode(' / ', $candidates));
    }
}
