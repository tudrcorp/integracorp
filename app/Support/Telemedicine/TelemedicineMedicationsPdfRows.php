<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\OperationInventory;

final class TelemedicineMedicationsPdfRows
{
    /**
     * Resuelve el nombre del medicamento cuando el médico eligió inventario TDC
     * (el campo manual `medicines` queda vacío) para que el recipe/PDF lo muestre.
     *
     * @param  array<int, mixed>  $medications
     * @return array<int, array{medicines: string, indications: string, duration: string, quantity: int|null, operation_inventory_id: int|string|null, covered_medicines: string, coverage: string}>
     */
    public static function normalize(array $medications): array
    {
        $inventoryIds = [];

        foreach ($medications as $row) {
            if (! is_array($row) || ! filled($row['operation_inventory_id'] ?? null)) {
                continue;
            }

            $inventoryIds[] = (int) $row['operation_inventory_id'];
        }

        $inventoriesById = $inventoryIds === []
            ? null
            : OperationInventory::query()
                ->whereIn('id', array_values(array_unique($inventoryIds)))
                ->get(['id', 'name', 'is_covered'])
                ->keyBy('id');

        $normalized = [];

        foreach ($medications as $row) {
            if (! is_array($row)) {
                continue;
            }

            $medicines = trim((string) ($row['medicines'] ?? ''));

            if ($medicines === '') {
                $medicines = trim((string) ($row['covered_medicines'] ?? ''));
            }

            $inventoryId = filled($row['operation_inventory_id'] ?? null) ? (int) $row['operation_inventory_id'] : null;
            $inventory = ($inventoryId !== null && $inventoriesById !== null)
                ? $inventoriesById->get($inventoryId)
                : null;

            if ($medicines === '' && $inventory !== null) {
                $medicines = trim((string) ($inventory->name ?? ''));
            }

            $inventoryIsCovered = $inventory !== null ? (bool) ($inventory->is_covered ?? false) : null;

            $normalized[] = [
                'medicines' => $medicines,
                'indications' => (string) ($row['indications'] ?? ''),
                'duration' => (string) ($row['duration'] ?? ''),
                'quantity' => self::quantityFromRow($row),
                'operation_inventory_id' => $row['operation_inventory_id'] ?? null,
                'covered_medicines' => (string) ($row['covered_medicines'] ?? ''),
                'coverage' => TelemedicineMedicationCoverage::pdfCoverageLabelFromRow($row, $inventoryIsCovered),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function quantityFromRow(array $row): ?int
    {
        if (! array_key_exists('quantity', $row) || $row['quantity'] === null || $row['quantity'] === '') {
            return null;
        }

        $quantity = (int) $row['quantity'];

        return $quantity > 0 ? $quantity : null;
    }

    /**
     * Cantidad a descontar de inventario. Si no viene informada, usa 1.
     *
     * @param  array<string, mixed>  $row
     */
    public static function quantityForInventoryDeduction(array $row): int
    {
        return self::quantityFromRow($row) ?? 1;
    }
}
