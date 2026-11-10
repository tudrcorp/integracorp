<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Borra el PDF temporal que {@see SendAffiliateFichaWhatsAppJob} dejó público
 * para que WhatsApp lo descargara. Solo acepta rutas de esa carpeta y con el
 * nombre que ella genera: nunca borra otra cosa.
 */
class DeleteAffiliateFichaPublicFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public string $relativePath) {}

    public function handle(): void
    {
        $pattern = '#^'.preg_quote(SendAffiliateFichaWhatsAppJob::PUBLIC_DIRECTORY, '#').'/[A-Za-z0-9]{48}\.pdf$#';

        if (preg_match($pattern, $this->relativePath) !== 1) {
            return;
        }

        $absolutePath = public_path('storage/'.$this->relativePath);

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }
}
