<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Log as ActivityLog;
use App\Support\WhiteCompanies\WhiteCompanySalesReportKey;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Verifica la llave impresa en el pie de un reporte de ventas.
 *
 * Las emisiones quedan registradas por SecurityAudit, así que la comprobación se
 * hace contra ese rastro: no hace falta una tabla adicional.
 */
class WhiteCompanySalesReportVerificationController extends Controller
{
    public function __invoke(Request $request, ?string $key = null): View
    {
        $key = trim((string) ($key ?? $request->query('llave', '')));

        if ($key === '') {
            return view('white-company-sales-report-verification', [
                'key' => '',
                'status' => 'empty',
                'issue' => null,
            ]);
        }

        $normalized = WhiteCompanySalesReportKey::normalize($key);

        $issue = ActivityLog::query()
            ->where('action', 'AUDIT_WHITE_COMPANY_SALES_REPORT_ISSUED')
            ->where('response', 'like', '%'.$normalized.'%')
            ->latest('id')
            ->first();

        if ($issue === null) {
            /** Segundo intento con el formato legible, por si se copió con guiones. */
            $issue = ActivityLog::query()
                ->where('action', 'AUDIT_WHITE_COMPANY_SALES_REPORT_ISSUED')
                ->where('response', 'like', '%'.$key.'%')
                ->latest('id')
                ->first();
        }

        if ($issue === null) {
            return view('white-company-sales-report-verification', [
                'key' => $key,
                'status' => 'not_found',
                'issue' => null,
            ]);
        }

        $payload = json_decode((string) $issue->response, true);
        $details = is_array($payload) ? ($payload['details'] ?? []) : [];

        $storedKey = (string) ($details['security_key'] ?? '');

        return view('white-company-sales-report-verification', [
            'key' => $key,
            'status' => WhiteCompanySalesReportKey::matches($key, $storedKey) ? 'valid' : 'mismatch',
            'issue' => [
                'company' => $details['white_company_name'] ?? '—',
                'from' => $details['from'] ?? '—',
                'to' => $details['to'] ?? '—',
                'rows' => $details['rows'] ?? 0,
                'totals' => $details['totals'] ?? [],
                'issued_at' => $issue->created_at?->format('d/m/Y H:i') ?? '—',
            ],
        ]);
    }
}
