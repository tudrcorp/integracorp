<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AdministrationAgencyReportsExportService;
use App\Support\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdministrationAgencyReportsExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse|BinaryFileResponse|Response
    {
        $validated = $request->validate([
            'report' => ['required', 'string', Rule::in(array_keys(AdministrationAgencyReportsExportService::reportLabels()))],
            'format' => ['required', 'string', Rule::in(['csv', 'xlsx'])],
        ]);

        SecurityAudit::log('AUDIT_ADMINISTRATION_AGENCIES_REPORT_EXPORTED', 'administration.agencies.reports.export', [
            'report' => $validated['report'],
            'format' => $validated['format'],
        ]);

        return match ($validated['format']) {
            'csv' => AdministrationAgencyReportsExportService::toCsv($validated['report']),
            'xlsx' => AdministrationAgencyReportsExportService::toXlsx($validated['report']),
        };
    }
}
