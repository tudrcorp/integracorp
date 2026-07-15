<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SystemNotificationKey;
use App\Support\Concerns\ReportsScheduledExecution;
use App\Support\Exports\IndividualAffiliationsExportService;
use App\Support\ScheduledTaskRunReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExportIndividualAffiliations implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use ReportsScheduledExecution;
    use SerializesModels;

    public int $timeout = 3900;

    public function handle(IndividualAffiliationsExportService $exportService): void
    {
        $this->runWithScheduledReport(
            'Exportación afiliaciones individuales',
            function () use ($exportService): void {
                ScheduledTaskRunReport::addExecutionDetail('Alcance', 'Todas las afiliaciones individuales con sus afiliados relacionados');
                ScheduledTaskRunReport::addExecutionDetail('Formato', 'Excel .xlsx (1 fila por afiliado; afiliación sin familiares = 1 fila)');
                ScheduledTaskRunReport::addExecutionDetail('Hojas', 'Afiliaciones + Afiliados en una sola hoja');

                $result = $exportService->create();

                ScheduledTaskRunReport::addMetric('Archivo generado', $result->filename);
                ScheduledTaskRunReport::addMetric('Tamaño del archivo', $result->formattedSize());
                ScheduledTaskRunReport::addMetric('Duración (seg)', number_format($result->durationSeconds, 2));
                ScheduledTaskRunReport::addMetric('Afiliaciones exportadas', $result->affiliationCount);
                ScheduledTaskRunReport::addMetric('Afiliados exportados', $result->affiliateCount);
                ScheduledTaskRunReport::addMetric('Filas en Excel', $result->rowCount);
                ScheduledTaskRunReport::addExecutionDetail('Ruta pública', $result->publicRelativePath);

                $maxAttachmentBytes = max(1, (int) config('scheduled-exports.max_whatsapp_attachment_mb', 50)) * 1048576;

                if ($result->bytes > $maxAttachmentBytes) {
                    ScheduledTaskRunReport::recordFailure('Archivo demasiado grande para adjuntar por WhatsApp');
                    ScheduledTaskRunReport::setFailureFootnote(
                        'El Excel se generó correctamente en el servidor, pero supera el límite configurado para envío por WhatsApp. Descárguelo desde storage/app/public/'.$result->publicRelativePath
                    );

                    return;
                }

                ScheduledTaskRunReport::setDocumentAttachment(
                    $result->publicRelativePath,
                    $result->filename,
                );

                $deleted = $exportService->purgeExpiredExports();
                ScheduledTaskRunReport::addMetric('Exportaciones antiguas eliminadas', $deleted);
            },
            'Genera un Excel .xlsx con todas las afiliaciones individuales y sus afiliados relacionados, y lo notifica a los destinatarios de Respaldo de Estructura.',
            [
                'Cada fila representa un afiliado ligado a su afiliación (datos de ambas tablas en la misma fila).',
                'Si una afiliación no tiene afiliados en la tabla affiliates, se exporta una fila solo con datos de la afiliación.',
                'Si la ejecución es exitosa, recibirás la imagen Integracorp + resumen y luego el .xlsx adjunto.',
                'Si hay fallas, el mensaje detallará el error sin adjuntar un archivo incompleto.',
                'Los destinatarios se gestionan en el Centro de notificaciones (Respaldo de Estructura).',
            ],
            SystemNotificationKey::StructureBackup,
        );
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('ExportIndividualAffiliations: FAILED', [
            'message' => $exception?->getMessage(),
        ]);
    }
}
