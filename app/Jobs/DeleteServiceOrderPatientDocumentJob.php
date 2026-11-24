<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\Operations\PatientWhatsApp\ServiceOrderPatientDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Borra la copia pública temporal del PDF de la orden que se le envió al paciente por
 * WhatsApp. Nunca borra nada fuera de esa carpeta.
 */
class DeleteServiceOrderPatientDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public string $relativePath) {}

    public function handle(): void
    {
        ServiceOrderPatientDocument::delete($this->relativePath);
    }
}
