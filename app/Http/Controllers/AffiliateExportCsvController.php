<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Exports\AffiliateCsvExportService;
use App\Support\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AffiliateExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'affiliate_export_csv_';

    private const TOKEN_TTL_SECONDS = 120;

    public function __invoke(Request $request): StreamedResponse
    {
        [$filters, $panel, $auditEvent, $auditRoute] = self::resolveExportContext($request);

        SecurityAudit::log($auditEvent, $auditRoute, [
            'plan_id' => $filters['plan_id'],
            'status' => $filters['status'],
            'affiliation_ids_count' => count($filters['affiliation_ids'] ?? []),
            'affiliate_ids_count' => count($filters['affiliate_ids'] ?? []),
            'exported_by_user_id' => Auth::id(),
            'panel' => $panel,
        ]);

        return app(AffiliateCsvExportService::class)->streamCsv($filters, $panel);
    }

    /**
     * @param  array{plan_id?: int|string|null, status?: string|null, affiliation_ids?: list<int|string>|null, affiliate_ids?: list<int|string>|null}  $filters
     */
    public static function storeFiltersAndGetToken(array $filters, string $panel): string
    {
        $token = bin2hex(random_bytes(16));

        $affiliationIds = array_values(array_filter(
            array_map('intval', (array) ($filters['affiliation_ids'] ?? [])),
            fn (int $id): bool => $id > 0,
        ));

        $affiliateIds = array_values(array_filter(
            array_map('intval', (array) ($filters['affiliate_ids'] ?? [])),
            fn (int $id): bool => $id > 0,
        ));

        Cache::put(self::CACHE_PREFIX.$token, [
            'plan_id' => filled($filters['plan_id'] ?? null) ? (int) $filters['plan_id'] : null,
            'status' => filled($filters['status'] ?? null) ? (string) $filters['status'] : null,
            'affiliation_ids' => $affiliationIds !== [] ? $affiliationIds : null,
            'affiliate_ids' => $affiliateIds !== [] ? $affiliateIds : null,
            'panel' => $panel,
        ], self::TOKEN_TTL_SECONDS);

        return $token;
    }

    /**
     * @return array{0: array{plan_id: ?int, status: ?string, affiliation_ids: ?list<int>, affiliate_ids: ?list<int>}, 1: string, 2: string, 3: string}
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
                    'affiliation_ids' => is_array($cached['affiliation_ids'] ?? null)
                        ? array_values(array_map('intval', $cached['affiliation_ids']))
                        : null,
                    'affiliate_ids' => is_array($cached['affiliate_ids'] ?? null)
                        ? array_values(array_map('intval', $cached['affiliate_ids']))
                        : null,
                ],
                $panel,
                $auditEvent,
                $auditRoute,
            ];
        }

        $validated = $request->validate([
            'plan_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', Rule::in(array_keys(AffiliateCsvExportService::statusOptions()))],
        ]);

        [$panel, $auditEvent, $auditRoute] = self::routeConfig((string) $request->route()?->getName());

        return [
            [
                'plan_id' => $validated['plan_id'] ?? null,
                'status' => $validated['status'] ?? null,
                'affiliation_ids' => null,
                'affiliate_ids' => null,
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
            'business.affiliates.export-csv' => [
                'business',
                'AUDIT_BUSINESS_AFFILIATES_CSV_EXPORT',
                'business.affiliates.export-csv',
            ],
            'administration.affiliates.export-csv' => [
                'administration',
                'AUDIT_ADMINISTRATION_AFFILIATES_CSV_EXPORT',
                'administration.affiliates.export-csv',
            ],
            'operations.affiliates.export-csv' => [
                'operations',
                'AUDIT_OPERATIONS_AFFILIATES_CSV_EXPORT',
                'operations.affiliates.export-csv',
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
                'AUDIT_BUSINESS_AFFILIATES_CSV_EXPORT',
                'business.affiliates.export-csv',
            ],
            'administration' => [
                'AUDIT_ADMINISTRATION_AFFILIATES_CSV_EXPORT',
                'administration.affiliates.export-csv',
            ],
            'operations' => [
                'AUDIT_OPERATIONS_AFFILIATES_CSV_EXPORT',
                'operations.affiliates.export-csv',
            ],
            default => abort(404),
        };
    }
}
