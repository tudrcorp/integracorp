<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\NotificationDeliveryStatus;
use App\Enums\TelegramRecipientStatus;
use App\Models\NotificationDelivery;
use App\Models\TelegramRecipient;
use App\Support\InternalNotifications\InternalNotificationDispatcher;
use App\Support\InternalNotifications\InternalNotificationFallback;
use App\Support\NotificationCenter\OutboundTelegram;
use App\Support\Telegram\TelegramApiException;
use App\Support\Telegram\TelegramBotClient;
use App\Support\Telegram\TelegramText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Entrega una notificación interna por Telegram.
 *
 * - Fallo transitorio (sin conexión, error 5xx): reintenta con espera creciente.
 * - Telegram pide frenar (429): espera exactamente lo que indica.
 * - Grupo convertido en supergrupo: actualiza el chat_id y reintenta.
 * - Bloqueó el bot / chat inexistente: marca el destino y pasa al respaldo.
 * - Agotados los intentos o cualquier otro error: pasa al respaldo (WhatsApp / correo).
 *
 * Idempotente: una entrega ya resuelta no se vuelve a enviar, y un candado evita
 * que dos workers la procesen a la vez.
 */
class SendTelegramDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Tope de vueltas por la cola, incluidas las esperas por 429 y por supergrupo. */
    public int $tries = 12;

    public int $timeout = 60;

    /**
     * @param  bool  $immediate  true en las alertas que no pueden esperar la cola (se ejecuta con
     *                           dispatchSync): ante cualquier error no reintenta, pasa al respaldo en el acto
     */
    public function __construct(public int $deliveryId, public bool $immediate = false) {}

    public function handle(): void
    {
        $lock = Cache::lock('telegram-delivery:'.$this->deliveryId, 60);

        if (! $lock->get()) {
            if (! $this->immediate) {
                $this->release(5);
            }

            return;
        }

        try {
            $this->deliver();
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        InternalNotificationFallback::run($this->deliveryId, 'Error inesperado al enviar por Telegram: '.($exception?->getMessage() ?? 'sin detalle'), $this->immediate);
    }

    private function deliver(): void
    {
        $delivery = NotificationDelivery::query()->with('recipient.user')->find($this->deliveryId);

        if (! $delivery instanceof NotificationDelivery || $delivery->status !== NotificationDeliveryStatus::Pending) {
            return;
        }

        if (! InternalNotificationDispatcher::telegramAvailable()) {
            InternalNotificationFallback::run($delivery->getKey(), 'Telegram está apagado o sin token.', $this->immediate);

            return;
        }

        $recipient = $delivery->recipient;

        if (! $recipient instanceof TelegramRecipient || ! $recipient->canReceive()) {
            InternalNotificationFallback::run($delivery->getKey(), 'El destino de Telegram ya no puede recibir ('.($recipient?->status->label() ?? 'eliminado').').', $this->immediate);

            return;
        }

        $delivery->increment('attempts');
        $attempts = (int) $delivery->attempts;

        try {
            $client = TelegramBotClient::make();
            $text = TelegramText::fromWhatsApp((string) $delivery->body);

            /** Los documentos (PDF de testigos) van siempre protegidos: se leen en el chat, no se reenvían. */
            $message = filled($delivery->attachment_path)
                ? $client->sendDocument((string) $recipient->chat_id, (string) $delivery->attachment_path, (string) ($delivery->attachment_name ?: 'documento.pdf'), $text, true)
                : $client->sendMessage((string) $recipient->chat_id, $text, $recipient->type->isPerson());
        } catch (TelegramApiException $exception) {
            $this->handleApiError($delivery, $recipient, $exception, $attempts);

            return;
        }

        $delivery->forceFill([
            'status' => NotificationDeliveryStatus::Sent,
            'provider_message_id' => isset($message['message_id']) ? (string) $message['message_id'] : null,
            'sent_at' => now(),
            'error' => null,
        ])->save();

        $recipient->forceFill(['last_sent_at' => now(), 'last_error' => null, 'last_error_at' => null])->save();

        OutboundTelegram::record($delivery, true);
    }

    private function handleApiError(NotificationDelivery $delivery, TelegramRecipient $recipient, TelegramApiException $exception, int $attempts): void
    {
        $delivery->forceFill(['error' => mb_substr($exception->getMessage(), 0, 1000)])->save();
        $recipient->forceFill(['last_error' => mb_substr($exception->getMessage(), 0, 500), 'last_error_at' => now()])->save();

        if ($exception->kind === TelegramApiException::KIND_MIGRATED && filled($exception->migrateToChatId)) {
            $recipient->forceFill(['chat_id' => $exception->migrateToChatId, 'updated_by' => 'telegram:supergrupo'])->save();
        }

        if ($this->immediate) {
            if ($exception->disablesRecipient()) {
                $recipient->forceFill(['status' => TelegramRecipientStatus::Blocked, 'updated_by' => 'telegram'])->save();
            }

            InternalNotificationFallback::run($delivery->getKey(), 'Aviso inmediato: Telegram no lo entregó en el primer intento ('.$exception->getMessage().'). Salió por el respaldo en el acto.', true);

            return;
        }

        if ($exception->kind === TelegramApiException::KIND_MIGRATED && filled($exception->migrateToChatId)) {
            $this->release(1);

            return;
        }

        if ($exception->kind === TelegramApiException::KIND_RATE_LIMITED) {
            $this->release(max(1, (int) $exception->retryAfter));

            return;
        }

        if ($exception->disablesRecipient()) {
            $recipient->forceFill(['status' => TelegramRecipientStatus::Blocked, 'updated_by' => 'telegram'])->save();
            InternalNotificationFallback::run($delivery->getKey(), 'Telegram no entrega a este destino: '.$exception->getMessage());

            return;
        }

        $maxAttempts = max(1, (int) config('telegram.max_attempts', 4));

        if ($exception->kind === TelegramApiException::KIND_TRANSIENT && $attempts < $maxAttempts) {
            $backoff = (array) config('telegram.backoff_seconds', [10, 30, 90]);
            $this->release((int) ($backoff[$attempts - 1] ?? end($backoff) ?: 60));

            return;
        }

        if ($exception->kind === TelegramApiException::KIND_UNAUTHORIZED) {
            Log::critical('Telegram: el token del bot es inválido o fue revocado.', ['delivery' => $delivery->uuid]);
        }

        InternalNotificationFallback::run($delivery->getKey(), 'No salió por Telegram tras '.$attempts.' '.($attempts === 1 ? 'intento' : 'intentos').': '.$exception->getMessage());
    }
}
