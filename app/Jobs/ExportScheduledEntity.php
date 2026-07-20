<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SystemNotificationKey;
use App\Support\Concerns\ReportsScheduledExecution;
use App\Support\Exports\AbstractScheduledEntityExportService;
use App\Support\ScheduledTaskRunReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class ExportScheduledEntity implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use ReportsScheduledExecution;
    use SerializesModels;

    public int $timeout = 3900;

    public function __construct(
        public string $exportKey,
    ) {}

    public function handle(): void
    {
        $meta = config('scheduled-exports.exports.'.$this->exportKey);

        if (! is_array($meta)) {
            throw new InvalidArgumentException("Exportación programada desconocida: {$this->exportKey}");
        }

        $serviceClass = $meta['service'] ?? null;

        if (! is_string($serviceClass) || ! is_subclass_of($serviceClass, AbstractScheduledEntityExportService::class)) {
            throw new InvalidArgumentException("Servicio de exportación inválido para: {$this->exportKey}");
        }

        /** @var AbstractScheduledEntityExportService $exportService */
        $exportService = app($serviceClass);

        $title = (string) ($meta['title'] ?? 'Exportación programada');
        $description = (string) ($meta['description'] ?? 'Genera un Excel .xlsx y lo envía por WhatsApp.');
        $executionDetails = is_array($meta['execution_details'] ?? null) ? $meta['execution_details'] : [];
        $readingNotes = is_array($meta['reading_notes'] ?? null) ? $meta['reading_notes'] : [];

        $this->runWithScheduledReport(
            $title,
            function () use ($exportService, $executionDetails, $meta): void {
                foreach ($executionDetails as $label => $value) {
                    ScheduledTaskRunReport::addExecutionDetail((string) $label, (string) $value);
                }

                $result = $exportService->create();

                ScheduledTaskRunReport::addMetric('Archivo generado', $result->filename);
                ScheduledTaskRunReport::addMetric('Tamaño del archivo', $result->formattedSize());
                ScheduledTaskRunReport::addMetric('Duración (seg)', number_format($result->durationSeconds, 2));
                ScheduledTaskRunReport::addMetric(
                    (string) ($meta['record_metric_label'] ?? 'Registros exportados'),
                    $result->affiliationCount,
                );

                if ($result->affiliateCount > 0) {
                    ScheduledTaskRunReport::addMetric(
                        (string) ($meta['note_metric_label'] ?? 'Notas incluidas'),
                        $result->affiliateCount,
                    );
                }

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
            $description,
            [
                ...$readingNotes,
                'Los destinatarios se gestionan en el Centro de notificaciones (Respaldo de Estructura).',
            ],
            SystemNotificationKey::StructureBackup,
        );
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('ExportScheduledEntity: FAILED', [
            'export_key' => $this->exportKey,
            'message' => $exception?->getMessage(),
        ]);
    }
}
