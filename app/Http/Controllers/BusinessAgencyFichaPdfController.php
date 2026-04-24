<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Services\AgencyFichaPdfService;
use App\Support\BusinessAgencyFichaPdfAccess;
use App\Support\SecurityAudit;
use Symfony\Component\HttpFoundation\Response;

class BusinessAgencyFichaPdfController extends Controller
{
    public function preview(Agency $agency): Response
    {
        self::prepareLongRunningPdfResponse();

        if (! BusinessAgencyFichaPdfAccess::userCanAccess($agency)) {
            SecurityAudit::log('AUDIT_BUSINESS_AGENCY_FICHA_ACCESS_DENIED', 'business.agencies.ficha-pdf.preview', [
                'agency_id' => $agency->getKey(),
                'reason' => 'forbidden',
            ]);

            abort(403);
        }

        $pdf = AgencyFichaPdfService::make($agency);
        $filename = AgencyFichaPdfService::filename($agency);

        SecurityAudit::log('AUDIT_BUSINESS_AGENCY_FICHA_VIEWED', 'business.agencies.ficha-pdf.preview', [
            'agency_id' => $agency->getKey(),
            'agency_name' => $agency->name_corporative,
            'filename' => $filename,
        ]);

        return $pdf->stream($filename);
    }

    public function download(Agency $agency): Response
    {
        self::prepareLongRunningPdfResponse();

        if (! BusinessAgencyFichaPdfAccess::userCanAccess($agency)) {
            SecurityAudit::log('AUDIT_BUSINESS_AGENCY_FICHA_ACCESS_DENIED', 'business.agencies.ficha-pdf.download', [
                'agency_id' => $agency->getKey(),
                'reason' => 'forbidden',
            ]);

            abort(403);
        }

        $pdf = AgencyFichaPdfService::make($agency);
        $filename = AgencyFichaPdfService::filename($agency);

        SecurityAudit::log('AUDIT_BUSINESS_AGENCY_FICHA_DOWNLOADED', 'business.agencies.ficha-pdf.download', [
            'agency_id' => $agency->getKey(),
            'agency_name' => $agency->name_corporative,
            'filename' => $filename,
        ]);

        return $pdf->download($filename);
    }

    private static function prepareLongRunningPdfResponse(): void
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        $limit = config('supplier-report.pdf_memory_limit');
        if (is_string($limit) && $limit !== '' && $limit !== '0') {
            @ini_set('memory_limit', $limit);
        }
    }
}
