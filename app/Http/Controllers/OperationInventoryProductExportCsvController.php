<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Exports\OperationInventoryProductCsvExportService;
use App\Support\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class OperationInventoryProductExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'operation_inventory_product_export_csv_';

    private const TOKEN_TTL_SECONDS = 120;

    public function __invoke(Request $request): StreamedResponse
    {
        $filters = self::resolveFilters($request);

        SecurityAudit::log('AUDIT_OPERATIONS_INVENTORY_PRODUCTS_CSV_EXPORT', 'operations.inventory-products.export-csv', [
            'category_id' => $filters['category_id'],
            'ubication_id' => $filters['ubication_id'],
            'existence_operator' => $filters['existence_operator'],
            'existence_value' => $filters['existence_value'],
            'exported_by_user_id' => Auth::id(),
        ]);

        return app(OperationInventoryProductCsvExportService::class)->streamCsv($filters);
    }

    /**
     * @param  array{
     *     category_id?: int|string|null,
     *     ubication_id?: int|string|null,
     *     existence_operator?: string|null,
     *     existence_value?: int|string|null
     * }  $filters
     */
    public static function storeFiltersAndGetToken(array $filters): string
    {
        $token = bin2hex(random_bytes(16));

        Cache::put(self::CACHE_PREFIX.$token, [
            'category_id' => filled($filters['category_id'] ?? null) ? (int) $filters['category_id'] : null,
            'ubication_id' => filled($filters['ubication_id'] ?? null) ? (int) $filters['ubication_id'] : null,
            'existence_operator' => filled($filters['existence_operator'] ?? null) ? (string) $filters['existence_operator'] : null,
            'existence_value' => filled($filters['existence_value'] ?? null) ? (int) $filters['existence_value'] : null,
        ], self::TOKEN_TTL_SECONDS);

        return $token;
    }

    /**
     * @return array{
     *     category_id: ?int,
     *     ubication_id: ?int,
     *     existence_operator: ?string,
     *     existence_value: ?int
     * }
     */
    private static function resolveFilters(Request $request): array
    {
        $token = $request->query('token');

        if (is_string($token) && $token !== '') {
            $cached = Cache::pull(self::CACHE_PREFIX.$token);

            if (! is_array($cached)) {
                abort(400, 'Token de exportación no válido o expirado.');
            }

            return [
                'category_id' => isset($cached['category_id']) ? (int) $cached['category_id'] : null,
                'ubication_id' => isset($cached['ubication_id']) ? (int) $cached['ubication_id'] : null,
                'existence_operator' => isset($cached['existence_operator']) ? (string) $cached['existence_operator'] : null,
                'existence_value' => isset($cached['existence_value']) ? (int) $cached['existence_value'] : null,
            ];
        }

        $validated = $request->validate([
            'category_id' => ['nullable', 'integer'],
            'ubication_id' => ['nullable', 'integer'],
            'existence_operator' => ['nullable', 'string', Rule::in(array_keys(OperationInventoryProductCsvExportService::existenceOperatorOptions()))],
            'existence_value' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            'ubication_id' => isset($validated['ubication_id']) ? (int) $validated['ubication_id'] : null,
            'existence_operator' => $validated['existence_operator'] ?? null,
            'existence_value' => isset($validated['existence_value']) ? (int) $validated['existence_value'] : null,
        ];
    }
}
