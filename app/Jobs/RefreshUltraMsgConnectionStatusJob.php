<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\Operations\PatientWhatsApp\UltraMsgConnectionStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Actualiza en segundo plano el estado de la instancia de WhatsApp que muestra
 * Operaciones → «WhatsApp al paciente». Único: varias pantallas abiertas piden una sola consulta.
 */
class RefreshUltraMsgConnectionStatusJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public int $uniqueFor = 60;

    public function handle(): void
    {
        UltraMsgConnectionStatus::refresh();
    }
}
