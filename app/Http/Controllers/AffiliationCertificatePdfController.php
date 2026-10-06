<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AffiliationCertificateIssue;
use App\Models\User;
use App\Services\AffiliationCertificateGeneratorService;
use App\Support\Affiliations\Certificates\AffiliationCertificateAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

/**
 * PDF de un certificado emitido por el generador: en línea para la vista previa
 * del panel, o como descarga con `?descargar=1`.
 */
class AffiliationCertificatePdfController extends Controller
{
    public function __invoke(Request $request, AffiliationCertificateIssue $issue, AffiliationCertificateGeneratorService $generator): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && AffiliationCertificateAccess::canUseFor($user, $issue->affiliation_type), 403);

        $disposition = $request->boolean('descargar') ? 'attachment' : 'inline';

        try {
            $content = $generator->renderPdf($issue);
        } catch (InvalidArgumentException) {
            // Documento grande dibujándose en cola: se avisa sin romper el iframe.
            return response(
                $issue->pdf_status === AffiliationCertificateIssue::PDF_FAILED
                    ? 'No se pudo generar este certificado. Vuelva a generarlo desde el panel.'
                    : 'El certificado se está generando. Recibirá una notificación cuando esté listo.',
                409,
                ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store'],
            );
        }

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.AffiliationCertificateGeneratorService::fileName($issue).'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
