<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AffiliationCertificateDelivery;
use App\Services\AffiliationCertificateDeliveryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Descarga del certificado enviado por correo como enlace (cuando pesaba
 * demasiado para adjuntarlo). Público pero firmado y con vencimiento: solo
 * quien recibió el correo tiene la URL, y deja de servir a los 30 días.
 */
class AffiliationCertificateDeliveryDownloadController extends Controller
{
    public function __invoke(Request $request, AffiliationCertificateDelivery $delivery, AffiliationCertificateDeliveryService $service): Response|BinaryFileResponse
    {
        if (! $delivery->linkIsActive()) {
            return $this->unavailable('Este enlace ya venció. Solicite un nuevo envío a su agente o a afiliaciones@tudrencasa.com.', 410);
        }

        try {
            $issue = $delivery->issue()->firstOrFail();
            $path = $service->filePath($delivery, $issue);
        } catch (Throwable) {
            return $this->unavailable('El documento no está disponible en este momento. Intente de nuevo más tarde.', 404);
        }

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.AffiliationCertificateDeliveryService::fileName($delivery, $issue).'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    private function unavailable(string $message, int $status): Response
    {
        return response($message, $status, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
