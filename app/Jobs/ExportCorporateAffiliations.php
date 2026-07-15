<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SystemNotificationKey;
use App\Support\Concerns\ReportsScheduledExecution;
use App\Support\Exports\CorporateAffiliationsExportService;
use App\Support\ScheduledTaskRunReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExportCorporateAffiliations implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use ReportsScheduledExecution;
    use SerializesModels;

    public int $timeout = 3900;

    public function handle(CorporateAffiliationsExportService $exportService): void
    {
        $this->runWithScheduledReport(
            'Exportación afiliaciones corporativas',
            function () use ($exportService): void {
                ScheduledTaskRunReport::addExecutionDetail('Alcance', 'Todas las afiliaciones corporativas con planes de contrato y afiliados relacionados');
                ScheduledTaskRunReport::addExecutionDetail('Formato', 'Excel .xlsx (1 fila por afiliado; planes sin afiliados = filas de plan; sin ambos = 1 fila de afiliación)');
                ScheduledTaskRunReport::addExecutionDetail('Hojas', 'Afiliación + Plan + Afiliado en una sola hoja');

                $result = $exportService->create();

                ScheduledTaskRunReport::addMetric('Archivo generado', $result->filename);
                ScheduledTaskRunReport::addMetric('Tamaño del archivo', $result->formattedSize());
                ScheduledTaskRunReport::addMetric('Duración (seg)', number_format($result->durationSeconds, 2));
                ScheduledTaskRunReport::addMetric('Afiliaciones corporativas exportadas', $result->affiliationCount);
                ScheduledTaskRunReport::addMetric('Líneas de plan exportadas', $result->planCount);
                ScheduledTaskRunReport::addMetric('Afiliados corporativos exportados', $result->affiliateCount);
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
            'Genera un Excel .xlsx con afiliaciones corporativas, sus planes de contrato y afiliados relacionados, y lo notifica a los destinatarios de Respaldo de Estructura.',
            [
                'Cada fila combina datos de afiliación corporativa, línea de plan (afilliation_corporate_plans) y afiliado (affiliate_corporates).',
                'Si hay afiliados, se incluye el plan de contrato que coincide con el plan_id del afiliado.',
                'Planes sin afiliados asociados se exportan en filas adicionales.',
                'Si la ejecución es exitosa, recibirás imagen Integracorp + resumen detallado + archivo .xlsx adjunto (en producción).',
                'Los destinatarios se gestionan en el Centro de notificaciones (Respaldo de Estructura).',
            ],
            SystemNotificationKey::StructureBackup,
        );
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('ExportCorporateAffiliations: FAILED', [
            'message' => $exception?->getMessage(),
        ]);
    }
}
