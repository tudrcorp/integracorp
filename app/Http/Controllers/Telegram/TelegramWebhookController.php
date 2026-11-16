<?php

declare(strict_types=1);

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Support\Telegram\TelegramUpdateHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Webhook de Telegram. Solo acepta llamadas con la clave secreta que se
 * registró con `php artisan telegram:set-webhook`; todo lo demás recibe 403.
 * Responde 200 siempre que la clave es válida: Telegram reintenta lo que no
 * recibe 200 y un error de proceso no debe provocar una tormenta de reintentos.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('telegram.webhook_secret', '');
        $received = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        if ($secret === '' || ! hash_equals($secret, $received)) {
            return response()->json(['ok' => false], 403);
        }

        $update = $request->json()->all();

        if (is_array($update) && $update !== []) {
            TelegramUpdateHandler::make()->handle($update);
        }

        return response()->json(['ok' => true]);
    }
}
