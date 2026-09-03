<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\OperationInventory;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineDoctor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Insumos médicos del inventario disponibles para que el médico registre lo que
 * consumió en la consulta o el seguimiento.
 *
 * Reutiliza las reglas de almacén y de descuento ya probadas para medicamentos
 * ({@see TelemedicineMedicationInventoryOptions}); lo único que cambia es la
 * categoría de producto sobre la que se filtra.
 */
final class TelemedicineSupplyInventoryOptions
{
    /**
     * Categoría en `operation_inventory_product_categories`. Se compara por
     * prefijo para tolerar «Insumos Medicos» / «Insumos Médicos».
     */
    public const CATEGORY_INSUMO_LIKE = 'INSUMO%';

    /**
     * @return Builder<OperationInventory>
     */
    public static function supplyInventoriesQuery(): Builder
    {
        return OperationInventory::query()
            ->where(function (Builder $query): void {
                $query
                    ->where('is_active', true)
                    ->orWhereNull('is_active');
            })
            ->whereHas('product.category', function (Builder $category): void {
                $category->whereRaw('UPPER(name) LIKE ?', [self::CATEGORY_INSUMO_LIKE]);
            });
    }

    /**
     * Cuando el consumo descuenta inventario solo se ofrecen insumos del almacén
     * del caso y con existencia; si no descuenta, se ofrece el catálogo completo.
     *
     * @return array<int|string, string>
     */
    public static function optionsForCase(?TelemedicineCase $case, ?TelemedicineDoctor $doctor = null): array
    {
        $doctor ??= $case?->telemedicineDoctor;

        if (TelemedicineMedicationInventoryOptions::shouldDeductInventory($doctor, $case)) {
            return self::warehouseSupplyOptions(
                (string) TelemedicineMedicationInventoryOptions::warehouseNameForBelongsTo($case?->belongs_to)
            );
        }

        return self::uniqueSupplyCatalogOptions();
    }

    /**
     * @return array<int|string, string>
     */
    public static function searchOptionsForCase(
        ?TelemedicineCase $case,
        string $search,
        ?TelemedicineDoctor $doctor = null,
        int $limit = 80,
    ): array {
        $options = self::optionsForCase($case, $doctor);
        $needle = mb_strtoupper(trim($search));

        if ($needle === '') {
            return array_slice($options, 0, $limit, preserve_keys: true);
        }

        $filtered = [];

        foreach ($options as $id => $label) {
            if (str_contains(mb_strtoupper((string) $label), $needle)) {
                $filtered[$id] = $label;
            }

            if (count($filtered) >= $limit) {
                break;
            }
        }

        return $filtered;
    }

    /**
     * Insumos del almacén TDG con existencia > 0.
     *
     * @return array<int|string, string>
     */
    public static function warehouseSupplyOptions(string $warehouseName): array
    {
        /** @var Collection<int, OperationInventory> $rows */
        $rows = self::supplyInventoriesQuery()
            ->where('existence', '>', 0)
            ->where(function (Builder $query) use ($warehouseName): void {
                TelemedicineMedicationInventoryOptions::constrainInventoryToWarehouse($query, $warehouseName);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'existence']);

        return $rows
            ->mapWithKeys(fn (OperationInventory $inventory): array => [
                $inventory->id => self::optionLabel($inventory, withExistence: true),
            ])
            ->all();
    }

    /**
     * Catálogo sin duplicar el mismo producto entre almacenes. Para médicos de
     * proveedor, que registran el consumo pero no descuentan existencias.
     *
     * @return array<int|string, string>
     */
    public static function uniqueSupplyCatalogOptions(): array
    {
        /** @var Collection<int, OperationInventory> $rows */
        $rows = self::supplyInventoriesQuery()
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'existence', 'operation_inventory_product_id']);

        return $rows
            ->unique(function (OperationInventory $inventory): string|int {
                return filled($inventory->operation_inventory_product_id)
                    ? 'product:'.$inventory->operation_inventory_product_id
                    : 'inventory:'.$inventory->id;
            })
            ->mapWithKeys(fn (OperationInventory $inventory): array => [
                $inventory->id => self::optionLabel($inventory, withExistence: false),
            ])
            ->all();
    }

    public static function optionLabel(OperationInventory $inventory, bool $withExistence): string
    {
        $label = trim((string) $inventory->name);
        $unit = trim((string) $inventory->unit);

        if ($unit !== '') {
            $label .= ' ('.$unit.')';
        }

        if ($withExistence) {
            $label .= ' · Disponible: '.(int) $inventory->existence;
        }

        return $label;
    }

    /**
     * Existencia del insumo en el almacén, para validar la cantidad del formulario.
     */
    public static function availableExistence(?int $operationInventoryId): ?int
    {
        if ($operationInventoryId === null || $operationInventoryId < 1) {
            return null;
        }

        $existence = self::supplyInventoriesQuery()
            ->whereKey($operationInventoryId)
            ->value('existence');

        return $existence === null ? null : (int) $existence;
    }

    /**
     * Normaliza las filas del repetidor: descarta vacías, agrega cantidades del
     * mismo insumo y fuerza cantidades enteras positivas.
     *
     * @param  mixed  $rows
     * @return array<int, array{operation_inventory_id: int, quantity: int}>
     */
    public static function normalizeRows($rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $normalized = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $inventoryId = (int) ($row['operation_inventory_id'] ?? 0);
            $quantity = (int) ($row['quantity'] ?? 0);

            if ($inventoryId < 1 || $quantity < 1) {
                continue;
            }

            $normalized[$inventoryId] = ($normalized[$inventoryId] ?? 0) + $quantity;
        }

        $result = [];

        foreach ($normalized as $inventoryId => $quantity) {
            $result[] = [
                'operation_inventory_id' => $inventoryId,
                'quantity' => $quantity,
            ];
        }

        return $result;
    }
}
