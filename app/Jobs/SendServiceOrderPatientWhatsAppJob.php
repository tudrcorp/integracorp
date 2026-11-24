<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\Operations\PatientWhatsApp\ServiceOrderPatientNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Envía al paciente el WhatsApp de su orden de servicio (documento + detalle + pregunta SI/NO).
 *
 * Único por notificación: aunque la tarea programada lo vuelva a despachar, no hay dos envíos
 * de la misma orden en cola a la vez. Si se agotan los intentos, la orden queda «Fallida» y
 * pasa la siguiente del mismo teléfono.
 */
class SendServiceOrderPatientWhatsAppJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public int $uniqueFor = 3600;

    public function __construct(public int $notificationId) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function uniqueId(): string
    {
        return 'service-order-patient-whatsapp:'.$this->notificationId;
    }

    public function handle(): void
    {
        ServiceOrderPatientNotifier::deliver($this->notificationId);
    }

    public function failed(?Throwable $exception): void
    {
        ServiceOrderPatientNotifier::markFailed($this->notificationId, mb_substr($exception?->getMessage() ?? 'El envío falló sin detalle.', 0, 2000));
    }
}
