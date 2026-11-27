<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CrmHandoffAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Borra del disco del CRM los archivos de clientes que pasaron el tiempo de resguardo. La fila queda (el hilo
 * muestra «ya no está disponible») y la copia que se agregó a un caso de telemedicina no se toca.
 */
class PruneCrmAttachmentsCommand extends Command
{
    protected $signature = 'crm-inbox:prune-attachments {--dry-run : Solo cuenta lo que se borraría}';

    protected $description = 'Borra los archivos de clientes de Atención WhatsApp con más días que el resguardo configurado';

    public function handle(): int
    {
        $days = max(1, (int) config('crm-inbox.media.retention_days', 180));
        $cutoff = now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');
        $purged = 0;
        $failed = 0;

        CrmHandoffAttachment::query()
            ->whereNull('purged_at')
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(200, function ($attachments) use ($dryRun, &$purged, &$failed): void {
                foreach ($attachments as $attachment) {
                    if ($dryRun) {
                        $purged++;

                        continue;
                    }

                    try {
                        $disk = Storage::disk((string) $attachment->disk);

                        if ($disk->exists((string) $attachment->path) && ! $disk->delete((string) $attachment->path)) {
                            $failed++;

                            continue;
                        }

                        $attachment->forceFill(['purged_at' => now()])->save();
                        $purged++;
                    } catch (Throwable $exception) {
                        $failed++;
                        report($exception);
                    }
                }
            });

        $this->info(($dryRun ? 'Se borrarían: ' : 'Archivos borrados: ').$purged.' (más de '.$days.' días).');

        if ($failed > 0) {
            $this->warn('No se pudieron borrar: '.$failed.'. Se reintenta en la próxima corrida.');
        }

        return self::SUCCESS;
    }
}
