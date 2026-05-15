<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AffiliationCorporate;
use App\Services\AffiliationCorporateFichaPdfService;
use App\Support\SecurityAudit;
use Symfony\Component\HttpFoundation\Response;

class AffiliationCorporateFichaPdfController extends Controller
{
    public function download(AffiliationCorporate $affiliationCorporate): Response
    {
        self::prepareLongRunningPdfResponse();

        $binary = AffiliationCorporateFichaPdfService::outputBinaryCached($affiliationCorporate);
        $filename = AffiliationCorporateFichaPdfService::downloadFilename($affiliationCorporate);

        SecurityAudit::log('AUDIT_ADMINISTRATION_AFFILIATION_CORPORATE_FICHA_DOWNLOADED', 'administration.affiliation-corporates.ficha.download', [
            'affiliation_corporate_id' => $affiliationCorporate->id,
            'affiliation_code' => $affiliationCorporate->code,
            'corporate_name' => $affiliationCorporate->name_corporate,
            'filename' => $filename,
        ]);

        return response($binary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    public function preview(AffiliationCorporate $affiliationCorporate): Response
    {
        self::prepareLongRunningPdfResponse();

        $binary = AffiliationCorporateFichaPdfService::outputBinaryCached($affiliationCorporate);
        $filename = AffiliationCorporateFichaPdfService::downloadFilename($affiliationCorporate);

        SecurityAudit::log('AUDIT_ADMINISTRATION_AFFILIATION_CORPORATE_FICHA_VIEWED', 'administration.affiliation-corporates.ficha.preview', [
            'affiliation_corporate_id' => $affiliationCorporate->id,
            'affiliation_code' => $affiliationCorporate->code,
            'corporate_name' => $affiliationCorporate->name_corporate,
            'filename' => $filename,
        ]);

        return response($binary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
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
