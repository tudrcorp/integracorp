<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OperationInventoryProductPresentation;
use App\Models\OperationInventoryProduct;
use App\Models\OperationInventoryProductCategory;
use InvalidArgumentException;
use RuntimeException;

class OperationInventoryProductCsvImporter
{
    /**
     * @return array{imported: int, updated: int, skipped: int, duplicate_codes: list<string>}
     */
    public function importFromPath(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("No se puede leer el CSV: {$path}");
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

            $header = array_map(fn (mixed $column): string => strtolower(trim((string) $column)), $header);
            $required = ['producto', 'presentacion', 'codigo', 'categoria_id'];

            foreach ($required as $column) {
                if (! in_array($column, $header, true)) {
                    throw new RuntimeException("Falta la columna requerida «{$column}» en el CSV.");
                }
            }

            $imported = 0;
            $updated = 0;
            $skipped = 0;
            /** @var array<string, int> $seenCodes */
            $seenCodes = [];
            /** @var list<string> $duplicateCodes */
            $duplicateCodes = [];

            while (($row = fgetcsv($handle)) !== false) {
                if ($this->isEmptyRow($row)) {
                    $skipped++;

                    continue;
                }

                $data = [];

                foreach ($header as $index => $column) {
                    $data[$column] = trim((string) ($row[$index] ?? ''));
                }

                $attributes = $this->normalizeRow($data, $seenCodes, $duplicateCodes);

                if ($attributes === null) {
                    $skipped++;

                    continue;
                }

                $categoryId = (int) $attributes['operation_inventory_product_category_id'];

                if (! OperationInventoryProductCategory::query()->whereKey($categoryId)->exists()) {
                    throw new RuntimeException("La categoría #{$categoryId} no existe para el producto «{$attributes['name']}».");
                }

                $product = OperationInventoryProduct::query()->updateOrCreate(
                    ['code' => $attributes['code']],
                    $attributes,
                );

                if ($product->wasRecentlyCreated) {
                    $imported++;
                } else {
                    $updated++;
                }
            }
        } finally {
            fclose($handle);
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'duplicate_codes' => $duplicateCodes,
        ];
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, int>  $seenCodes
     * @param  list<string>  $duplicateCodes
     * @return array{
     *     operation_inventory_product_category_id: int,
     *     code: string,
     *     name: string,
     *     cost: string,
     *     unit: string,
     *     presentation: string,
     *     is_active: bool,
     *     created_by: string
     * }|null
     */
    public function normalizeRow(array $data, array &$seenCodes = [], array &$duplicateCodes = []): ?array
    {
        $name = trim((string) ($data['producto'] ?? ''));
        $code = trim((string) ($data['codigo'] ?? ''));
        $presentationRaw = trim((string) ($data['presentacion'] ?? ''));
        $categoryId = (int) trim((string) ($data['categoria_id'] ?? ''));

        if ($name === '' || $code === '' || $presentationRaw === '' || $categoryId <= 0) {
            return null;
        }

        $presentation = OperationInventoryProductPresentation::fromStored($presentationRaw);

        if ($presentation === null) {
            return null;
        }

        return [
            'operation_inventory_product_category_id' => $categoryId,
            'code' => $this->uniqueCode($code, $seenCodes, $duplicateCodes),
            'name' => $name,
            'cost' => '0.00',
            'unit' => 'UNIDAD',
            'presentation' => $presentation->value,
            'is_active' => true,
            'created_by' => 'INTEGRACORP',
        ];
    }

    /**
     * @param  list<mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, int>  $seenCodes
     * @param  list<string>  $duplicateCodes
     */
    private function uniqueCode(string $code, array &$seenCodes, array &$duplicateCodes): string
    {
        if (! isset($seenCodes[$code])) {
            $seenCodes[$code] = 1;

            return $code;
        }

        $seenCodes[$code]++;
        $suffix = $seenCodes[$code];
        $unique = "{$code}-{$suffix}";
        $duplicateCodes[] = "{$code} → {$unique}";

        return $unique;
    }
}
