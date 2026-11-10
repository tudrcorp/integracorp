<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Filament\Administration\Resources\Sales\Tables\SalesTable;
use App\Http\Requests\RegenerateReciboPagoRequest;
use App\Http\Requests\SendReciboPagoEmailRequest;
use App\Models\Sale;
use App\Support\Sales\SaleReceiptEmailDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Throwable;

class ReciboPagoController extends Controller
{
    public function regenerateAsync(RegenerateReciboPagoRequest $request, Sale $sale): JsonResponse
    {
        $validated = $request->validated();

        $ok = SalesTable::runRegenerateReciboPago($sale, $validated);

        if (! $ok) {
            return response()->json([
                'ok' => false,
                'message' => 'Error al regenerar el recibo de pago.',
            ], 422);
        }

        $filename = 'RDP-'.$sale->invoice_number.'.pdf';
        $version = (string) time();
        $directUrl = asset('storage/reciboDePago/'.$filename).'?t='.$version;

        return response()->json([
            'ok' => true,
            'preview_url' => $directUrl,
            'direct_url' => $directUrl,
        ]);
    }

    /**
     * Envía el recibo ya generado con el copy elegido para el afiliado y para el agente / agencia.
     */
    public function sendEmail(SendReciboPagoEmailRequest $request, Sale $sale): JsonResponse
    {
        $validated = $request->validated();
        $testMode = filled($validated['test_email'] ?? null);

        try {
            $deliveries = SaleReceiptEmailDelivery::deliver(
                $sale,
                affiliateTemplateId: isset($validated['affiliate_template_id']) ? (int) $validated['affiliate_template_id'] : null,
                responsibleTemplateId: isset($validated['responsible_template_id']) ? (int) $validated['responsible_template_id'] : null,
                testMode: $testMode,
                testEmail: $testMode ? (string) $validated['test_email'] : null,
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            LogController::log((int) Auth::id(), 'EXCEPTION', 'administration.sales.send-receipt-email', $exception->getMessage());

            return response()->json([
                'ok' => false,
                'message' => 'No se pudo enviar el recibo. Intente de nuevo; si persiste, avise a Sistemas.',
            ], 500);
        }

        LogController::log((int) Auth::id(), $testMode ? 'Prueba de envío de recibo de pago por correo' : 'Envío de recibo de pago por correo', 'Modulo Ventas', 'ENVIAR');

        $sentTo = collect($deliveries)->flatMap(fn (array $delivery): array => $delivery['to'])->unique()->implode(', ');

        return response()->json([
            'ok' => true,
            'message' => $testMode
                ? 'Prueba enviada a '.$sentTo.': '.count($deliveries).' '.(count($deliveries) === 1 ? 'correo' : 'correos').', uno por copy elegido. No se notificó a nadie más.'
                : 'Recibo enviado a '.$sentTo.'. Los testigos configurados reciben su copia. Llegará en unos instantes.',
            'deliveries' => $deliveries,
        ]);
    }
}
