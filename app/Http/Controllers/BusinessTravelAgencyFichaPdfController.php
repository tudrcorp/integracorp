<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TravelAgency;
use App\Services\TravelAgencyFichaPdfService;
use App\Support\BusinessTravelAgencyFichaPdfAccess;
use App\Support\SecurityAudit;
use Symfony\Component\HttpFoundation\Response;

class BusinessTravelAgencyFichaPdfController extends Controller
{
    public function preview(TravelAgency $travelAgency): Response
    {
        self::prepareLongRunningPdfResponse();

        if (! BusinessTravelAgencyFichaPdfAccess::userCanAccess($travelAgency)) {
            SecurityAudit::log('AUDIT_BUSINESS_TRAVEL_AGENCY_FICHA_ACCESS_DENIED', 'business.travel-agencies.ficha-pdf.preview', [
                'travel_agency_id' => $travelAgency->getKey(),
                'reason' => 'forbidden',
            ]);

            abort(403);
        }

        $pdf = TravelAgencyFichaPdfService::make($travelAgency);
        $filename = TravelAgencyFichaPdfService::filename($travelAgency);

        SecurityAudit::log('AUDIT_BUSINESS_TRAVEL_AGENCY_FICHA_VIEWED', 'business.travel-agencies.ficha-pdf.preview', [
            'travel_agency_id' => $travelAgency->getKey(),
            'travel_agency_name' => $travelAgency->name,
            'filename' => $filename,
        ]);

        return $pdf->stream($filename);
    }

    public function download(TravelAgency $travelAgency): Response
    {
        self::prepareLongRunningPdfResponse();

        if (! BusinessTravelAgencyFichaPdfAccess::userCanAccess($travelAgency)) {
            SecurityAudit::log('AUDIT_BUSINESS_TRAVEL_AGENCY_FICHA_ACCESS_DENIED', 'business.travel-agencies.ficha-pdf.download', [
                'travel_agency_id' => $travelAgency->getKey(),
                'reason' => 'forbidden',
            ]);

            abort(403);
        }

        $pdf = TravelAgencyFichaPdfService::make($travelAgency);
        $filename = TravelAgencyFichaPdfService::filename($travelAgency);

        SecurityAudit::log('AUDIT_BUSINESS_TRAVEL_AGENCY_FICHA_DOWNLOADED', 'business.travel-agencies.ficha-pdf.download', [
            'travel_agency_id' => $travelAgency->getKey(),
            'travel_agency_name' => $travelAgency->name,
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
