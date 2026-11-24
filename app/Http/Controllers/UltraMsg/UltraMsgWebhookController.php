<?php

declare(strict_types=1);

namespace App\Http\Controllers\UltraMsg;

use App\Http\Controllers\Controller;
use App\Support\Operations\PatientWhatsApp\UltraMsgConnectionStatus;
use App\Support\Operations\PatientWhatsApp\UltraMsgInboundHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Webhook de UltraMsg (mensajes recibidos y checks de entrega). Público, sin sesión ni CSRF.
 *
 * UltraMsg no firma sus llamadas: la protección es la clave secreta en la URL
 * (ULTRAMSG_WEBHOOK_SECRET) y, si se configura, el id de la instancia. Con la clave válida
 * siempre responde 200: UltraMsg reintenta lo que no recibe 200 y un error de proceso no
 * debe provocar una tormenta de reintentos (cada mensaje se guarda una sola vez).
 */
class UltraMsgWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret): JsonResponse
    {
        $expected = (string) config('services.ultramsg_webhook.secret', '');

        if ($expected === '' || ! hash_equals($expected, $secret)) {
            return response()->json(['ok' => false], 403);
        }

        $payload = $request->json()->all();

        if (! is_array($payload) || $payload === []) {
            $payload = $request->all();
        }

        $instanceId = trim((string) config('services.ultramsg_webhook.instance_id', ''));
        $received = trim((string) ($payload['instanceId'] ?? ''));

        if ($instanceId !== '' && $received !== '' && ltrim(str_ireplace('instance', '', $received), '0') !== ltrim(str_ireplace('instance', '', $instanceId), '0')) {
            return response()->json(['ok' => false], 403);
        }

        UltraMsgConnectionStatus::markWebhookReceived();

        try {
            UltraMsgInboundHandler::handle($payload);
        } catch (Throwable $exception) {
            report($exception);
        }

        return response()->json(['ok' => true]);
    }
}
