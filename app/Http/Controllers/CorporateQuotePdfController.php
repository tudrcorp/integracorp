<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CorporateQuote;
use App\Support\CorporateQuotePdfGenerator;
use App\Support\SecurityAudit;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CorporateQuotePdfController extends Controller
{
    public function download(CorporateQuote $corporateQuote): BinaryFileResponse
    {
        if (! CorporateQuotePdfGenerator::regenerateIfMissing($corporateQuote)) {
            SecurityAudit::log('AUDIT_BUSINESS_CORPORATE_QUOTE_PDF_DOWNLOAD_FAILED', 'business.corporate-quotes.download', [
                'panel' => 'business',
                'corporate_quote_id' => $corporateQuote->id,
                'code' => $corporateQuote->code,
                'reason' => 'file_not_found_or_generation_failed',
            ]);

            abort(404, 'El documento asociado a la cotización no se encuentra disponible.');
        }

        $path = public_path('storage/quotes/'.$corporateQuote->code.'.pdf');

        SecurityAudit::log('AUDIT_BUSINESS_CORPORATE_QUOTE_PDF_DOWNLOADED', 'business.corporate-quotes.download', [
            'panel' => 'business',
            'corporate_quote_id' => $corporateQuote->id,
            'code' => $corporateQuote->code,
            'path' => $path,
        ]);

        return response()->download($path);
    }
}
