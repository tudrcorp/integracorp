<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\QrDestinationVersion;
use App\Support\QrCenter\QrCenterAccess;
use App\Support\QrCenter\QrCenterStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Imagen de una versión del QR para el centro de QR (miniaturas, historial,
 * descargas). Vive en el disco privado: solo la ve quien administra el centro.
 */
class QrCenterImageController extends Controller
{
    public function __invoke(Request $request, QrDestinationVersion $version): Response
    {
        abort_unless(QrCenterAccess::allows($request->user()), 403);

        if (! QrCenterStorage::exists($version->image_path)) {
            abort(404);
        }

        $name = 'qr-'.$version->qr_destination_id.'-v'.$version->version.'.png';

        return response()->file(QrCenterStorage::absolutePath((string) $version->image_path), [
            'Content-Type' => 'image/png',
            'Content-Disposition' => ($request->boolean('descargar') ? 'attachment' : 'inline').'; filename="'.$name.'"',
            // Cada versión es inmutable: su imagen nunca cambia.
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
