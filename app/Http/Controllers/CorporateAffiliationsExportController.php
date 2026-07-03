<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Exports\CorporateAffiliationsExportService;
use App\Support\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CorporateAffiliationsExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse|BinaryFileResponse
    {
        $validated = $request->validate([
            'plan_id' => ['nullable', 'integer'],
            'affiliate_status' => ['nullable', 'string', Rule::in(array_keys(CorporateAffiliationsExportService::affiliateStatusOptions()))],
            'format' => ['required', 'string', Rule::in(['csv', 'xlsx'])],
        ]);

        [$auditEvent, $auditRoute] = self::auditConfig((string) $request->route()?->getName());

        $filters = [
            'plan_id' => $validated['plan_id'] ?? null,
            'affiliate_status' => $validated['affiliate_status'] ?? null,
        ];

        SecurityAudit::log($auditEvent, $auditRoute, [
            'plan_id' => $filters['plan_id'],
            'affiliate_status' => $filters['affiliate_status'],
            'format' => $validated['format'],
            'exported_by_user_id' => Auth::id(),
        ]);

        $service = app(CorporateAffiliationsExportService::class);

        return $validated['format'] === 'csv'
            ? $service->streamCsv($filters)
            : $service->downloadXlsx($filters);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function auditConfig(string $routeName): array
    {
        return match ($routeName) {
            'business.affiliation-corporates.export-report' => [
                'AUDIT_BUSINESS_CORPORATE_AFFILIATIONS_EXPORT',
                'business.affiliation-corporates.export-report',
            ],
            'administration.affiliation-corporates.export-report' => [
                'AUDIT_ADMINISTRATION_CORPORATE_AFFILIATIONS_EXPORT',
                'administration.affiliation-corporates.export-report',
            ],
            'operations.affiliate-corporates.export-report' => [
                'AUDIT_OPERATIONS_AFFILIATE_CORPORATES_EXPORT',
                'operations.affiliate-corporates.export-report',
            ],
            default => abort(404),
        };
    }
}
