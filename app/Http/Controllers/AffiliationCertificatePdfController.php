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
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * PDF de un certificado emitido por el generador: completo en línea, como
 * descarga con `?descargar=1`, o la vista previa liviana (portada y primera hoja
 * de carnets) con `?vista=previa`, disponible aun mientras el completo se dibuja.
 *
 * Se entrega el archivo guardado en streaming: un corporativo grande pesa decenas
 * de MB y cargarlo entero en memoria por cada vista previa no escala.
 */
class AffiliationCertificatePdfController extends Controller
{
    public function __invoke(Request $request, AffiliationCertificateIssue $issue, AffiliationCertificateGeneratorService $generator): Response|BinaryFileResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User && AffiliationCertificateAccess::canUseFor($user, $issue->affiliation_type), 403);

        $disposition = $request->boolean('descargar') ? 'attachment' : 'inline';

        $preview = $request->query('vista') === 'previa';

        try {
            $path = $preview ? $generator->previewFile($issue) : $generator->pdfFile($issue);
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

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.($preview ? 'Vista-previa-' : '').AffiliationCertificateGeneratorService::fileName($issue).'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
