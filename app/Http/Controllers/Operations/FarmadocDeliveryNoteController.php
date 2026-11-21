<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Filament\Operations\Resources\FarmadocOrders\FarmadocOrderResource;
use App\Filament\Operations\Resources\FarmadocOrders\Pages\ViewFarmadocOrder;
use App\Http\Controllers\Controller;
use App\Models\FarmadocOrder;
use App\Services\Farmadoc\FarmadocAllyClient;
use App\Support\Farmadoc\FarmadocException;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Nota de entrega de un pedido aceptado por Farmadoc. Se descarga del API con el token
 * del aliado y se entrega al analista: el enlace y el token de Farmadoc nunca salen del servidor.
 */
class FarmadocDeliveryNoteController extends Controller
{
    public function __invoke(Request $request, FarmadocOrder $farmadocOrder, FarmadocAllyClient $client): Response|RedirectResponse
    {
        abort_unless(FarmadocOrderResource::canAccess(), 403);
        abort_unless($farmadocOrder->hasDeliveryNote(), 404, 'Este pedido todavía no tiene nota de entrega.');

        try {
            $note = $client->deliveryNote($farmadocOrder->order_number);
        } catch (FarmadocException $exception) {
            Notification::make()
                ->title('No se pudo obtener la nota de entrega del pedido '.$farmadocOrder->order_number)
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return redirect()->to(ViewFarmadocOrder::getUrl(['record' => $farmadocOrder], panel: 'operations'));
        }

        $disposition = $request->boolean('descargar') ? 'attachment' : 'inline';

        return response($note['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$note['filename'].'"',
            'Content-Length' => (string) strlen($note['content']),
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
