<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Exports\AffiliateCorporateCsvExportService;
use App\Support\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AffiliateCorporateExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'affiliate_corporate_export_csv_';

    private const TOKEN_TTL_SECONDS = 120;

    public function __invoke(Request $request): StreamedResponse
    {
        [$filters, $panel, $auditEvent, $auditRoute] = self::resolveExportContext($request);

        SecurityAudit::log($auditEvent, $auditRoute, [
            'plan_id' => $filters['plan_id'],
            'status' => $filters['status'],
            'affiliation_corporate_ids_count' => count($filters['affiliation_corporate_ids'] ?? []),
            'affiliate_corporate_ids_count' => count($filters['affiliate_corporate_ids'] ?? []),
            'exported_by_user_id' => Auth::id(),
            'panel' => $panel,
        ]);

        return app(AffiliateCorporateCsvExportService::class)->streamCsv($filters, $panel);
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function auditMetadataForPanel(string $panel): array
    {
        return self::auditConfigForPanel($panel);
    }

    /**
     * @param  array{plan_id?: int|string|null, status?: string|null, affiliation_corporate_ids?: list<int|string>|null, affiliate_corporate_ids?: list<int|string>|null}  $filters
     */
    public static function storeFiltersAndGetToken(array $filters, string $panel): string
    {
        $token = bin2hex(random_bytes(16));

        $affiliationCorporateIds = array_values(array_filter(
            array_map('intval', (array) ($filters['affiliation_corporate_ids'] ?? [])),
            fn (int $id): bool => $id > 0,
        ));

        $affiliateCorporateIds = array_values(array_filter(
            array_map('intval', (array) ($filters['affiliate_corporate_ids'] ?? [])),
            fn (int $id): bool => $id > 0,
        ));

        Cache::put(self::CACHE_PREFIX.$token, [
            'plan_id' => filled($filters['plan_id'] ?? null) ? (int) $filters['plan_id'] : null,
            'status' => filled($filters['status'] ?? null) ? (string) $filters['status'] : null,
            'affiliation_corporate_ids' => $affiliationCorporateIds !== [] ? $affiliationCorporateIds : null,
            'affiliate_corporate_ids' => $affiliateCorporateIds !== [] ? $affiliateCorporateIds : null,
            'panel' => $panel,
        ], self::TOKEN_TTL_SECONDS);

        return $token;
    }

    /**
     * @return array{0: array{plan_id: ?int, status: ?string, affiliation_corporate_ids: ?list<int>, affiliate_corporate_ids: ?list<int>}, 1: string, 2: string, 3: string}
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
                    'plan_id' => $cached['plan_id'] ?? null,
                    'status' => $cached['status'] ?? null,
                    'affiliation_corporate_ids' => is_array($cached['affiliation_corporate_ids'] ?? null)
                        ? array_values(array_map('intval', $cached['affiliation_corporate_ids']))
                        : null,
                    'affiliate_corporate_ids' => is_array($cached['affiliate_corporate_ids'] ?? null)
                        ? array_values(array_map('intval', $cached['affiliate_corporate_ids']))
                        : null,
                ],
                $panel,
                $auditEvent,
                $auditRoute,
            ];
        }

        $validated = $request->validate([
            'plan_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', Rule::in(array_keys(AffiliateCorporateCsvExportService::statusOptions()))],
        ]);

        [$panel, $auditEvent, $auditRoute] = self::routeConfig((string) $request->route()?->getName());

        return [
            [
                'plan_id' => $validated['plan_id'] ?? null,
                'status' => $validated['status'] ?? null,
                'affiliation_corporate_ids' => null,
                'affiliate_corporate_ids' => null,
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
            'business.affiliate-corporates.export-csv' => [
                'business',
                'AUDIT_BUSINESS_AFFILIATE_CORPORATES_CSV_EXPORT',
                'business.affiliate-corporates.export-csv',
            ],
            'administration.affiliate-corporates.export-csv' => [
                'administration',
                'AUDIT_ADMINISTRATION_AFFILIATE_CORPORATES_CSV_EXPORT',
                'administration.affiliate-corporates.export-csv',
            ],
            'operations.affiliate-corporates.export-csv' => [
                'operations',
                'AUDIT_OPERATIONS_AFFILIATE_CORPORATES_CSV_EXPORT',
                'operations.affiliate-corporates.export-csv',
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
                'AUDIT_BUSINESS_AFFILIATE_CORPORATES_CSV_EXPORT',
                'business.affiliate-corporates.export-csv',
            ],
            'administration' => [
                'AUDIT_ADMINISTRATION_AFFILIATE_CORPORATES_CSV_EXPORT',
                'administration.affiliate-corporates.export-csv',
            ],
            'operations' => [
                'AUDIT_OPERATIONS_AFFILIATE_CORPORATES_CSV_EXPORT',
                'operations.affiliate-corporates.export-csv',
            ],
            default => abort(404),
        };
    }
}
