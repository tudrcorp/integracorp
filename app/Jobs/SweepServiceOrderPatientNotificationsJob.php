<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\Operations\PatientWhatsApp\ServiceOrderPatientNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Schema;

/**
 * Revisión periódica del WhatsApp al paciente de las órdenes de servicio: marca «Sin respuesta»
 * las preguntas vencidas, retoma envíos que se perdieron en la cola y envía la siguiente orden
 * de cada teléfono que quedó libre.
 */
class SweepServiceOrderPatientNotificationsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 300;

    public int $uniqueFor = 900;

    public function handle(): void
    {
        if (! Schema::hasTable('service_order_patient_notifications')) {
            return;
        }

        ServiceOrderPatientNotifier::sweep();
    }
}
