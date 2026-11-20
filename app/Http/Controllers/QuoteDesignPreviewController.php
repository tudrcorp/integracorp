<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Filament\Business\Resources\QuoteDesigns\QuoteDesignResource;
use App\Support\Quotes\Design\QuoteDesignPreview;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Muestra en el navegador la vista previa de un diseño de cotización. La URL es firmada y el
 * archivo vive en una carpeta a nombre del usuario: nadie más puede abrirlo.
 */
class QuoteDesignPreviewController extends Controller
{
    public function __invoke(string $token): Response
    {
        abort_unless(QuoteDesignResource::canAccess(), 403);
        abort_unless(Str::isUuid($token), 404);

        $path = QuoteDesignPreview::path((int) Auth::id(), $token);
        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404, 'La vista previa venció. Vuelva a generarla desde el diseño.');

        return response((string) $disk->get($path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="vista-previa-diseno-cotizacion.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
