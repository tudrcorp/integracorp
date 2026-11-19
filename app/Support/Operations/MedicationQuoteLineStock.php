<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationInventory;
use App\Models\OperationInventoryProductStock;
use App\Models\OperationInventoryUbication;

/**
 * Existencia de inventario que se muestra bajo el nombre del ítem en la cotización.
 * Solo medicamentos: laboratorios, estudios y especialistas no tienen stock.
 * El usuario de un proveedor no ve existencias.
 */
final class MedicationQuoteLineStock
{
    public static function hint(?string $category, ?string $label): ?string
    {
        if ($category !== 'Medicamento' || ! MedicationInvoiceWizard::userCanSeeInventoryAvailability()) {
            return null;
        }

        return self::text(MedicationInventoryCatalog::searchSummary((string) $label));
    }

    /**
     * El botón del encabezado se muestra si algún medicamento no está en el inventario,
     * o si está y su existencia no supera el stock mínimo registrado.
     * La existencia es la misma que se lee bajo el ítem. El mínimo es la suma del stock mínimo
     * de los almacenes activos de los productos que coinciden con ese nombre.
     *
     * @param  array<int|string, mixed>|null  $lines
     */
    public static function listNeedsSupplierOrder(?array $lines): bool
    {
        if (! self::hasMedicationLine($lines) || ! MedicationInvoiceWizard::userCanSeeInventoryAvailability()) {
            return false;
        }

        foreach ($lines as $line) {
            if (! is_array($line) || ($line['category'] ?? null) !== 'Medicamento') {
                continue;
            }

            $label = $line['label'] ?? null;

            if (! is_string($label) || trim($label) === '') {
                continue;
            }

            if (self::labelNeedsReorder($label)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Un medicamento sin coincidencias en el catálogo también pide reposición.
     *
     * @param  list<array{existence: int, minimum: int}>  $rows
     */
    public static function lineNeedsSupplierOrder(int $matches, array $rows): bool
    {
        if ($matches === 0) {
            return true;
        }

        return self::medicationNeedsReorder($rows);
    }

    /**
     * @param  list<array{existence: int, minimum: int}>  $rows
     */
    public static function medicationNeedsReorder(array $rows): bool
    {
        if ($rows === []) {
            return false;
        }

        $existence = 0;
        $minimum = 0;

        foreach ($rows as $row) {
            $existence += (int) $row['existence'];
            $minimum += (int) $row['minimum'];
        }

        return $existence <= $minimum;
    }

    /**
     * @param  array<int|string, mixed>|null  $lines
     */
    private static function hasMedicationLine(?array $lines): bool
    {
        if ($lines === null) {
            return false;
        }

        foreach ($lines as $line) {
            if (is_array($line) && ($line['category'] ?? null) === 'Medicamento') {
                return true;
            }
        }

        return false;
    }

    private static function labelNeedsReorder(string $label): bool
    {
        $normalized = mb_strtoupper(trim($label));

        return once(function () use ($normalized): bool {
            $products = MedicationInventoryCatalog::search($normalized, 200);

            if ($products->isEmpty()) {
                return self::lineNeedsSupplierOrder(0, []);
            }

            $productIds = $products->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
            $ubicationIds = OperationInventoryUbication::query()
                ->where('is_active', true)
                ->pluck('id');

            $inventories = OperationInventory::query()
                ->whereIn('operation_inventory_product_id', $productIds)
                ->whereIn('operation_inventory_ubication_id', $ubicationIds)
                ->get(['operation_inventory_product_id', 'operation_inventory_ubication_id', 'existence', 'min_stock']);

            if ($inventories->isEmpty()) {
                return self::lineNeedsSupplierOrder($products->count(), []);
            }

            $stocks = OperationInventoryProductStock::query()
                ->whereIn('operation_inventory_product_id', $productIds)
                ->get(['operation_inventory_product_id', 'operation_inventory_ubication_id', 'existence'])
                ->keyBy(fn (OperationInventoryProductStock $stock): string => $stock->operation_inventory_product_id.':'.$stock->operation_inventory_ubication_id);

            $rows = [];

            foreach ($inventories as $inventory) {
                $stock = $stocks->get($inventory->operation_inventory_product_id.':'.$inventory->operation_inventory_ubication_id);

                $rows[] = [
                    'existence' => MedicationInventoryCatalog::sellableExistence($inventory->existence, $stock?->existence),
                    'minimum' => (int) $inventory->min_stock,
                ];
            }

            return self::lineNeedsSupplierOrder($products->count(), $rows);
        });
    }

    /**
     * @param  array{matches: int, units: int}  $summary
     */
    public static function text(array $summary): string
    {
        if ((int) ($summary['matches'] ?? 0) === 0) {
            return 'Sin existencia registrada en el inventario.';
        }

        return 'Existencia: '.number_format((int) ($summary['units'] ?? 0), 0, ',', '.');
    }
}
