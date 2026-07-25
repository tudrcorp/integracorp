<?php

declare(strict_types=1);

namespace App\Support\Exports;

use App\Enums\OperationInventoryProductPresentation;
use App\Models\OperationInventoryProduct;
use App\Models\OperationInventoryUbication;
use App\Support\CsvExportStream;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class OperationInventoryProductCsvExportService
{
    /**
     * @return array<string, string>
     */
    public static function existenceOperatorOptions(): array
    {
        return [
            'gt' => 'Mayor a',
            'lt' => 'Menor a',
        ];
    }

    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'Código',
            'Nombre',
            'Categoría',
            'Costo',
            'Unidad',
            'Presentación',
            'Almacén',
            'Existencia',
            'Activo',
            'Creado por',
            'Creado',
        ];
    }

    /**
     * @param  array{
     *     category_id?: int|string|null,
     *     ubication_id?: int|string|null,
     *     existence_operator?: string|null,
     *     existence_value?: int|string|null
     * }  $filters
     */
    public function streamCsv(array $filters): StreamedResponse
    {
        $filename = 'productos_inventario_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($filters): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, self::headers());

            $ubicationId = filled($filters['ubication_id'] ?? null) ? (int) $filters['ubication_id'] : null;
            $ubicationName = $ubicationId !== null
                ? (string) (OperationInventoryUbication::query()->whereKey($ubicationId)->value('name') ?? '—')
                : 'TODOS';

            self::query($filters)
                ->orderBy('name')
                ->lazyById(200)
                ->each(function (OperationInventoryProduct $product) use ($handle, $ubicationId, $ubicationName): void {
                    fputcsv($handle, self::row($product, $ubicationId, $ubicationName));
                });

            fclose($handle);
        }, 200, self::downloadHeaders($filename));
    }

    /**
     * @return array<string, string>
     */
    public static function downloadHeaders(string $filename): array
    {
        return [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Transfer-Encoding' => 'binary',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Pragma' => 'public',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    /**
     * @param  array{
     *     category_id?: int|string|null,
     *     ubication_id?: int|string|null,
     *     existence_operator?: string|null,
     *     existence_value?: int|string|null
     * }  $filters
     * @return Builder<OperationInventoryProduct>
     */
    public static function query(array $filters): Builder
    {
        $categoryId = filled($filters['category_id'] ?? null) ? (int) $filters['category_id'] : null;
        $ubicationId = filled($filters['ubication_id'] ?? null) ? (int) $filters['ubication_id'] : null;
        $operator = filled($filters['existence_operator'] ?? null) ? (string) $filters['existence_operator'] : null;
        $existenceValue = filled($filters['existence_value'] ?? null) ? (int) $filters['existence_value'] : null;

        $query = OperationInventoryProduct::query()
            ->with(['category:id,name']);

        if ($categoryId !== null) {
            $query->where('operation_inventory_product_category_id', $categoryId);
        }

        if ($ubicationId !== null) {
            $query->withSum(
                ['stocks as filtered_existence' => fn (Builder $stock): Builder => $stock->where('operation_inventory_ubication_id', $ubicationId)],
                'existence',
            );
        } else {
            $query->withSum('stocks as filtered_existence', 'existence');
        }

        if ($operator !== null && $existenceValue !== null && in_array($operator, ['gt', 'lt'], true)) {
            $comparison = $operator === 'gt' ? '>' : '<';

            if ($ubicationId !== null) {
                $query->whereHas('stocks', function (Builder $stock) use ($ubicationId, $comparison, $existenceValue): void {
                    $stock->where('operation_inventory_ubication_id', $ubicationId)
                        ->where('existence', $comparison, $existenceValue);
                });
            } else {
                $query->whereRaw(
                    '(select coalesce(sum(s.existence), 0) from operation_inventory_product_stocks s where s.operation_inventory_product_id = operation_inventory_products.id) '.$comparison.' ?',
                    [$existenceValue]
                );
            }
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    public static function row(
        OperationInventoryProduct $product,
        ?int $ubicationId,
        string $ubicationName,
    ): array {
        $existence = (int) ($product->filtered_existence ?? $product->totalExistence());

        return [
            (string) $product->code,
            (string) $product->name,
            (string) ($product->category?->name ?? '—'),
            number_format((float) $product->cost, 2, '.', ''),
            (string) ($product->unit ?? '—'),
            OperationInventoryProductPresentation::labelFromMixed($product->presentation),
            $ubicationName,
            (string) $existence,
            $product->is_active ? 'Sí' : 'No',
            (string) ($product->created_by ?? '—'),
            $product->created_at?->format('d/m/Y H:i') ?? '—',
        ];
    }
}
