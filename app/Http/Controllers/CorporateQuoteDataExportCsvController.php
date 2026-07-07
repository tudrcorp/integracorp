<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Exports\CorporateQuoteDataCsvExportService;
use App\Support\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CorporateQuoteDataExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'corporate_quote_data_export_csv_';

    private const TOKEN_TTL_SECONDS = 120;

    public function __invoke(Request $request): StreamedResponse
    {
        [$filters, $panel, $auditEvent, $auditRoute] = self::resolveExportContext($request);

        SecurityAudit::log($auditEvent, $auditRoute, [
            'corporate_quote_ids_count' => count($filters['corporate_quote_ids'] ?? []),
            'corporate_quote_data_ids_count' => count($filters['corporate_quote_data_ids'] ?? []),
            'exported_by_user_id' => Auth::id(),
            'panel' => $panel,
        ]);

        return app(CorporateQuoteDataCsvExportService::class)->streamCsv($filters, $panel);
    }

    /**
     * @param  array{corporate_quote_ids?: list<int|string>|null, corporate_quote_data_ids?: list<int|string>|null}  $filters
     */
    public static function storeFiltersAndGetToken(array $filters, string $panel): string
    {
        $token = bin2hex(random_bytes(16));

        $corporateQuoteIds = array_values(array_filter(
            array_map('intval', (array) ($filters['corporate_quote_ids'] ?? [])),
            fn (int $id): bool => $id > 0,
        ));

        $corporateQuoteDataIds = array_values(array_filter(
            array_map('intval', (array) ($filters['corporate_quote_data_ids'] ?? [])),
            fn (int $id): bool => $id > 0,
        ));

        Cache::put(self::CACHE_PREFIX.$token, [
            'corporate_quote_ids' => $corporateQuoteIds !== [] ? $corporateQuoteIds : null,
            'corporate_quote_data_ids' => $corporateQuoteDataIds !== [] ? $corporateQuoteDataIds : null,
            'panel' => $panel,
        ], self::TOKEN_TTL_SECONDS);

        return $token;
    }

    /**
     * @return array{0: array{corporate_quote_ids: ?list<int>, corporate_quote_data_ids: ?list<int>}, 1: string, 2: string, 3: string}
     */
    private static function resolveExportContext(Request $request): array
    {
        $token = $request->query('token');

        if (is_string($token) && $token !== '') {
            $cached = Cache::pull(self::CACHE_PREFIX.$token);

            if (! is_array($cached)) {
                abort(400, 'Token de exportación no válido o expirado.');
            }

            $panel = (string) ($cached['panel'] ?? 'business');
            [$auditEvent, $auditRoute] = self::auditConfigForPanel($panel);

            return [
                [
                    'corporate_quote_ids' => is_array($cached['corporate_quote_ids'] ?? null)
                        ? array_values(array_map('intval', $cached['corporate_quote_ids']))
                        : null,
                    'corporate_quote_data_ids' => is_array($cached['corporate_quote_data_ids'] ?? null)
                        ? array_values(array_map('intval', $cached['corporate_quote_data_ids']))
                        : null,
                ],
                $panel,
                $auditEvent,
                $auditRoute,
            ];
        }

        [$panel, $auditEvent, $auditRoute] = self::routeConfig((string) $request->route()?->getName());

        return [
            [
                'corporate_quote_ids' => null,
                'corporate_quote_data_ids' => null,
            ],
            $panel,
            $auditEvent,
            $auditRoute,
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private static function routeConfig(string $routeName): array
    {
        return match ($routeName) {
            'business.corporate-quote-data.export-csv' => [
                'business',
                'AUDIT_BUSINESS_CORPORATE_QUOTE_DATA_CSV_EXPORT',
                'business.corporate-quote-data.export-csv',
            ],
            default => abort(404),
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function auditConfigForPanel(string $panel): array
    {
        return match ($panel) {
            'business' => [
                'AUDIT_BUSINESS_CORPORATE_QUOTE_DATA_CSV_EXPORT',
                'business.corporate-quote-data.export-csv',
            ],
            default => abort(404),
        };
    }
}
