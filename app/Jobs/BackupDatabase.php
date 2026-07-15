<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SystemNotificationKey;
use App\Support\Concerns\ReportsScheduledExecution;
use App\Support\Database\DatabaseBackupService;
use App\Support\ScheduledTaskRunReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackupDatabase implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use ReportsScheduledExecution;
    use SerializesModels;

    public int $timeout = 3900;

    public function handle(DatabaseBackupService $backupService): void
    {
        $this->runWithScheduledReport(
            'Respaldo de base de datos',
            function () use ($backupService): void {
                $connectionName = (string) config('database.default');
                $connection = config("database.connections.{$connectionName}");

                ScheduledTaskRunReport::addExecutionDetail('Conexión', $connectionName);
                ScheduledTaskRunReport::addExecutionDetail('Motor', (string) ($connection['driver'] ?? 'desconocido'));
                ScheduledTaskRunReport::addExecutionDetail('Base de datos', (string) ($connection['database'] ?? 'desconocida'));
                ScheduledTaskRunReport::addExecutionDetail('Contenido del .sql', 'Estructura + datos (tablas, triggers, rutinas y eventos en MySQL/MariaDB)');

                $result = $backupService->create($connectionName);

                ScheduledTaskRunReport::addMetric('Archivo generado', $result->filename);
                ScheduledTaskRunReport::addMetric('Tamaño del respaldo', $result->formattedSize());
                ScheduledTaskRunReport::addMetric('Duración (seg)', number_format($result->durationSeconds, 2));
                ScheduledTaskRunReport::addExecutionDetail('Ruta pública', $result->publicRelativePath);

                $maxAttachmentBytes = max(1, (int) config('backup.max_whatsapp_attachment_mb', 50)) * 1048576;

                if ($result->bytes > $maxAttachmentBytes) {
                    ScheduledTaskRunReport::recordFailure('Archivo demasiado grande para adjuntar por WhatsApp');
                    ScheduledTaskRunReport::setFailureFootnote(
                        'El respaldo se generó correctamente en el servidor, pero supera el límite configurado para envío por WhatsApp. Descárguelo desde storage/app/public/'.$result->publicRelativePath
                    );

                    return;
                }

                ScheduledTaskRunReport::setDocumentAttachment(
                    $result->publicRelativePath,
                    $result->filename,
                );

                $deleted = $backupService->purgeExpiredBackups();
                ScheduledTaskRunReport::addMetric('Respaldos antiguos eliminados', $deleted);
            },
            'Genera un respaldo completo de la base de datos en formato .sql (estructura y datos) y lo notifica a los destinatarios del Centro de notificaciones.',
            [
                'El archivo .sql incluye tablas, datos y, en MySQL/MariaDB, triggers, rutinas y eventos.',
                'Si la ejecución es exitosa, el mensaje de WhatsApp incluirá el archivo .sql adjunto (si no supera el límite).',
                'Si hay fallas, el mensaje detallará el error sin adjuntar un respaldo incompleto.',
                'Los respaldos antiguos se eliminan según la retención configurada (backup.retention_days).',
                'Los destinatarios se gestionan en el Centro de notificaciones (Respaldo de base de datos).',
            ],
            SystemNotificationKey::DatabaseBackup,
        );
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('BackupDatabase: FAILED', [
            'message' => $exception?->getMessage(),
        ]);
    }
}
