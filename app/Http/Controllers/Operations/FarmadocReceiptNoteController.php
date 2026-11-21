<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Filament\Operations\Resources\FarmadocOrders\FarmadocOrderResource;
use App\Http\Controllers\Controller;
use App\Models\FarmadocOrder;
use App\Support\Farmadoc\FarmadocReceiptNote;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Nota de recibido y aprobado de un pedido a Farmadoc, para los analistas.
 */
class FarmadocReceiptNoteController extends Controller
{
    public function __invoke(Request $request, FarmadocOrder $farmadocOrder): Response
    {
        abort_unless(FarmadocOrderResource::canAccess(), 403);
        abort_unless(FarmadocReceiptNote::available($farmadocOrder), 404, 'Este pedido todavía no se registró en el inventario.');

        $content = FarmadocReceiptNote::render($farmadocOrder);
        $disposition = $request->boolean('descargar') ? 'attachment' : 'inline';

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.FarmadocReceiptNote::filename($farmadocOrder).'"',
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
