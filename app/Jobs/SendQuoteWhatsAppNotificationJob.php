<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\QuoteWhatsAppNotification;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Envía por WhatsApp los avisos del módulo de cotizaciones, fuera del request.
 *
 * Antes la pantalla esperaba a UltraMsg —seis llamadas en serie con timeout de
 * 30 s, y encima dentro de la transacción del panel— para recién entonces
 * redirigir al detalle. Ahora la cotización se guarda, el analista sigue
 * trabajando y este job informa en el panel cómo terminó el envío.
 */
class SendQuoteWhatsAppNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * Un envío parcial no se repite (ver `QuoteWhatsAppNotification::send()`),
     * así que reintentar solo ocurre cuando nadie recibió nada.
     */
    public int $tries = 3;

    /**
     * Espera creciente: UltraMsg suele restablecerse en segundos, no en horas.
     *
     * @var list<int>
     */
    public array $backoff = [15, 60, 180];

    /**
     * Seis mensajes con timeout de 30 s caben de sobra en este margen.
     */
    public int $timeout = 240;

    public bool $failOnTimeout = true;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly QuoteWhatsAppNotification $notification,
        public readonly array $payload,
        public readonly ?int $notifiableUserId = null,
    ) {}

    public function handle(): void
    {
        /**
         * El candado cubre el doble clic y el reintento simultáneo; se libera
         * al terminar el job para que un reenvío posterior sea posible.
         */
        $lock = Cache::lock($this->notification->lockKey($this->payload), $this->timeout + 60);

        if (! $lock->get()) {
            Log::info('quote-whatsapp: envío ya en curso, se omite el duplicado', [
                'notification' => $this->notification->value,
                'code' => $this->payload['code'] ?? null,
            ]);

            return;
        }

        try {
            $enviado = $this->notification->send($this->payload);
        } catch (Throwable $exception) {
            Log::error('quote-whatsapp: error al enviar', [
                'notification' => $this->notification->value,
                'code' => $this->payload['code'] ?? null,
                'attempt' => $this->attempts(),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            $lock->release();
        }

        if (! $enviado) {
            /** Nadie lo recibió: reintentar es seguro y no duplica mensajes. */
            throw new RuntimeException(
                'El servicio de WhatsApp no entregó el aviso de la cotización '.($this->payload['code'] ?? '—').'.'
            );
        }

        Log::info('quote-whatsapp: aviso entregado', [
            'notification' => $this->notification->value,
            'code' => $this->payload['code'] ?? null,
            'attempt' => $this->attempts(),
        ]);

        $this->notifyUser(
            Notification::make()
                ->title($this->notification->successTitle())
                ->body($this->notification->successBody($this->payload))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->iconColor('success')
                ->success()
        );
    }

    /**
     * Agotados los reintentos, el analista debe enterarse: el aviso no salió.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('quote-whatsapp: aviso no entregado tras agotar los reintentos', [
            'notification' => $this->notification->value,
            'code' => $this->payload['code'] ?? null,
            'message' => $exception?->getMessage(),
        ]);

        $this->notifyUser(
            Notification::make()
                ->title($this->notification->failureTitle())
                ->body($this->notification->failureBody($this->payload))
                ->icon('heroicon-o-exclamation-triangle')
                ->iconColor('danger')
                ->danger()
        );
    }

    /**
     * La notificación va a la bandeja del analista que hizo la acción. Si el
     * usuario ya no existe, el resultado queda en el log y nada revienta.
     */
    private function notifyUser(Notification $notification): void
    {
        if ($this->notifiableUserId === null) {
            return;
        }

        $user = User::query()->find($this->notifiableUserId);

        if (! $user instanceof User) {
            return;
        }

        $url = $this->payload['url'] ?? null;

        if (is_string($url) && $url !== '') {
            $notification->actions([
                Action::make('ver')
                    ->label('Ver cotización')
                    ->button()
                    ->url($url),
            ]);
        }

        try {
            $notification->sendToDatabase($user);
        } catch (Throwable $exception) {
            Log::warning('quote-whatsapp: no se pudo dejar la notificación en el panel', [
                'user_id' => $this->notifiableUserId,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
