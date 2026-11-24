<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\NotificationCenter\OutboundWhatsApp;
use App\Support\Operations\PatientWhatsApp\ServiceOrderPatientNotifier;
use App\Support\Operations\PatientWhatsApp\UltraMsgClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use RuntimeException;

/**
 * Respuesta automática al paciente por WhatsApp: la confirmación de su SI/NO o el
 * recordatorio de que responda solo SI o NO. Texto simple, sin imagen.
 */
class SendServiceOrderPatientWhatsAppTextJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public string $phone,
        public string $body,
        public string $kind,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(): void
    {
        $decision = OutboundWhatsApp::decide($this->phone, [
            'catalog' => ServiceOrderPatientNotifier::CATALOG_KEY,
            'origin' => self::class.'::handle',
        ]);
        $params = ['to' => $this->phone, 'body' => $this->body];

        if ($decision->blocked()) {
            OutboundWhatsApp::recordBlocked($decision, $params);

            return;
        }

        $result = UltraMsgClient::sendText($this->phone, $this->body);
        OutboundWhatsApp::record($decision, $result['ok'], $params, $result['error']);

        if (! $result['ok']) {
            throw new RuntimeException('No se pudo enviar la respuesta automática ('.$this->kind.') al paciente: '.$result['error']);
        }
    }
}
