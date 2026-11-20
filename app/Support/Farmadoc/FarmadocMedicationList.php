<?php

declare(strict_types=1);

namespace App\Support\Farmadoc;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Catálogo de Las Delicias listo para la pantalla del analista.
 * Descarta precio, costo y existencia aunque el API los envíe.
 */
final class FarmadocMedicationList
{
    public const LABEL_PRESCRIPTION = 'Requiere fórmula';

    public const LABEL_CONTROLLED = 'Controlado';

    /**
     * @param  list<mixed>  $rows
     * @return list<array<string, mixed>>
     */
    public static function fromApiRows(array $rows): array
    {
        $medications = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $productId = $row['product_id'] ?? null;
            $name = $row['name'] ?? null;

            if (! is_numeric($productId) || (int) $productId < 1 || ! is_string($name) || trim($name) === '') {
                continue;
            }

            $ingredient = self::ingredientLabel($row['active_ingredient'] ?? null);
            $presentation = self::joinedText($row['presentation'] ?? null, $row['presentation_type'] ?? null);

            $medications[] = [
                '__key' => (string) (int) $productId,
                'product_id' => (int) $productId,
                'sku' => self::nullableText($row['sku'] ?? null),
                'barcode' => self::nullableText($row['barcode'] ?? null),
                'name' => trim($name),
                'brand' => self::nullableText($row['brand'] ?? null),
                'description' => self::nullableText($row['description'] ?? null),
                'product_category_name' => self::nullableText($row['product_category_name'] ?? null),
                'active_ingredient' => $ingredient,
                'concentration' => self::nullableText($row['concentration'] ?? null),
                'presentation' => $presentation,
                'unit_of_measure' => self::nullableText($row['unit_of_measure'] ?? null),
                'requires_prescription' => (bool) ($row['requires_prescription'] ?? false),
                'is_controlled_substance' => (bool) ($row['is_controlled_substance'] ?? false),
                'formula_label' => ($row['requires_prescription'] ?? false) ? 'Requiere fórmula' : 'Venta libre',
                'controlled_label' => ($row['is_controlled_substance'] ?? false) ? 'Controlado' : 'No',
            ];
        }

        return array_values($medications);
    }

    /**
     * @param  list<array<string, mixed>>  $medications
     */
    public static function paginate(
        array $medications,
        ?string $search,
        int $page,
        int $perPage,
        ?string $sortColumn = null,
        ?string $sortDirection = null,
    ): LengthAwarePaginator {
        $perPage = in_array($perPage, [10, 25, 50], true) ? $perPage : 25;
        $page = max(1, $page);
        $filtered = self::filter($medications, $search);
        $sorted = self::sort($filtered, $sortColumn, $sortDirection);
        $total = count($sorted);
        $offset = ($page - 1) * $perPage;

        return new LengthAwarePaginator(
            array_slice($sorted, $offset, $perPage),
            $total,
            $perPage,
            $page,
            ['path' => '/'],
        );
    }

    /**
     * Filtros de la tabla del analista. `$filters` llega tal cual lo guarda Filament
     * (`['requires_prescription' => ['isActive' => true], …]`).
     *
     * @param  list<array<string, mixed>>  $medications
     * @param  array<string, mixed>|null  $filters
     * @param  list<int>  $orderedProductIds
     * @return list<array<string, mixed>>
     */
    public static function applyFilters(array $medications, ?array $filters, array $orderedProductIds = []): array
    {
        $isActive = static fn (string $name): bool => is_array($filters[$name] ?? null)
            && (bool) ($filters[$name]['isActive'] ?? false);

        $onlyPrescription = $isActive('requires_prescription');
        $onlyControlled = $isActive('controlled');
        $onlyInOrder = $isActive('in_order');

        if (! $onlyPrescription && ! $onlyControlled && ! $onlyInOrder) {
            return $medications;
        }

        $ordered = array_fill_keys(array_map(intval(...), $orderedProductIds), true);

        return array_values(array_filter(
            $medications,
            static fn (array $medication): bool => (! $onlyPrescription || (bool) ($medication['requires_prescription'] ?? false))
                && (! $onlyControlled || (bool) ($medication['is_controlled_substance'] ?? false))
                && (! $onlyInOrder || isset($ordered[(int) ($medication['product_id'] ?? 0)])),
        ));
    }

    /**
     * Segunda línea de la columna «Medicamento»: concentración · presentación · marca.
     *
     * @param  array<string, mixed>  $medication
     */
    public static function detailsLine(array $medication): ?string
    {
        $parts = array_values(array_unique(array_filter(
            [
                $medication['concentration'] ?? null,
                $medication['presentation'] ?? null,
                $medication['brand'] ?? null,
            ],
            static fn (mixed $value): bool => is_string($value) && trim($value) !== '',
        )));

        return $parts === [] ? null : implode(' · ', array_map(trim(...), $parts));
    }

    /**
     * Etiquetas de la columna «Condiciones»: solo lo que exige atención.
     *
     * @param  array<string, mixed>  $medication
     * @return list<string>
     */
    public static function conditionLabels(array $medication): array
    {
        $labels = [];

        if ((bool) ($medication['requires_prescription'] ?? false)) {
            $labels[] = self::LABEL_PRESCRIPTION;
        }

        if ((bool) ($medication['is_controlled_substance'] ?? false)) {
            $labels[] = self::LABEL_CONTROLLED;
        }

        return $labels;
    }

    /**
     * @param  list<array<string, mixed>>  $medications
     * @return list<array<string, mixed>>
     */
    public static function filter(array $medications, ?string $search): array
    {
        $term = Str::lower(trim((string) $search));

        if ($term === '') {
            return $medications;
        }

        return array_values(array_filter(
            $medications,
            function (array $medication) use ($term): bool {
                $haystack = Str::lower(implode(' ', array_filter([
                    $medication['name'] ?? null,
                    $medication['sku'] ?? null,
                    $medication['barcode'] ?? null,
                    $medication['brand'] ?? null,
                    $medication['active_ingredient'] ?? null,
                    $medication['concentration'] ?? null,
                    $medication['presentation'] ?? null,
                    $medication['product_category_name'] ?? null,
                ], fn (mixed $value): bool => is_string($value) && $value !== '')));

                return str_contains($haystack, $term);
            },
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $medications
     * @return list<array<string, mixed>>
     */
    private static function sort(array $medications, ?string $sortColumn, ?string $sortDirection): array
    {
        $column = in_array($sortColumn, ['name', 'barcode', 'sku', 'concentration', 'presentation'], true)
            ? $sortColumn
            : 'name';
        $descending = $sortDirection === 'desc';

        usort($medications, function (array $left, array $right) use ($column, $descending): int {
            $comparison = strcasecmp((string) ($left[$column] ?? ''), (string) ($right[$column] ?? ''));

            return $descending ? -$comparison : $comparison;
        });

        return $medications;
    }

    private static function ingredientLabel(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        if (! is_array($value)) {
            return null;
        }

        $parts = [];

        foreach (Arr::flatten($value) as $part) {
            if (! is_scalar($part)) {
                continue;
            }

            $text = trim((string) $part);

            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    private static function joinedText(mixed $left, mixed $right): ?string
    {
        $parts = array_values(array_filter([
            self::nullableText($left),
            self::nullableText($right),
        ]));

        return $parts === [] ? null : implode(' · ', array_unique($parts));
    }

    private static function nullableText(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
