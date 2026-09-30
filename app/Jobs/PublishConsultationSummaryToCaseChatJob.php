<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\Telemedicine\ConsultationChatSummaryNotifier;
use App\Support\Telemedicine\ConsultationChatSummaryPublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Escribe en el chat del caso el resumen de la consulta y avisa en la campana a quienes pueden ver
 * el caso. Corre en cola y después del commit: el doctor no espera al guardar y el resumen ve los
 * medicamentos y órdenes ya registrados.
 */
class PublishConsultationSummaryToCaseChatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $consultationId,
        public ?int $authorUserId = null,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [15, 60, 180];
    }

    public function handle(): void
    {
        $message = ConsultationChatSummaryPublisher::publish($this->consultationId, $this->authorUserId);

        if ($message === null || ! $message->isConsultationSummary()) {
            return;
        }

        ConsultationChatSummaryNotifier::notifyOnce($message);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('No se pudo publicar el resumen de la consulta en el chat del caso.', [
            'telemedicine_consultation_patient_id' => $this->consultationId,
            'exception' => $exception,
        ]);
    }
}
