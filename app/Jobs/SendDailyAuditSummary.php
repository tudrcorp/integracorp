<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\AuditCompletionSummaryMail;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\Audit\AuditCompletionReport;
use App\Support\Concerns\ReportsScheduledExecution;
use App\Support\ScheduledTaskRunReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendDailyAuditSummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReportsScheduledExecution, SerializesModels;

    /**
     * Correo destinatario del reporte de auditorías.
     */
    private const REPORT_EMAIL = 'solrodriguez@tudrencasa.com';

    /**
     * Teléfonos que reciben el reporte por WhatsApp.
     *
     * @var array<int, string>
     */
    private const REPORT_PHONES = [
        '04127018390',
        '04143027250',
        '04245718777',
    ];

    public function __construct() {}

    public function handle(): void
    {
        $this->runWithScheduledReport(
            'Reporte diario de auditorías completas',
            function (): void {
                $this->dispatchAuditSummary();
            },
            'Contabiliza las agencias, agentes, afiliaciones individuales y corporativas con auditoría completa (todos sus puntos verificados) y envía el resumen por WhatsApp y correo.',
            [
                '*Auditoría completa* = el registro tiene verificados todos los puntos de control de su catálogo.',
                'Las auditorías parciales no se contabilizan en los totales.',
            ],
        );
    }

    private function dispatchAuditSummary(): void
    {
        $counts = AuditCompletionReport::counts();

        ScheduledTaskRunReport::addMetric('Agencias auditadas', $counts['agencies']['audited'].' / '.$counts['agencies']['total']);
        ScheduledTaskRunReport::addMetric('Agentes auditados', $counts['agents']['audited'].' / '.$counts['agents']['total']);
        ScheduledTaskRunReport::addMetric('Afiliaciones individuales auditadas', $counts['individual_affiliations']['audited'].' / '.$counts['individual_affiliations']['total']);
        ScheduledTaskRunReport::addMetric('Afiliaciones corporativas auditadas', $counts['corporate_affiliations']['audited'].' / '.$counts['corporate_affiliations']['total']);
        ScheduledTaskRunReport::addMetric('Total auditado por completo', $counts['totals']['audited'].' / '.$counts['totals']['total']);

        $this->dispatchWhatsApp($counts);
        $this->sendEmail($counts);
    }

    /**
     * @param  array<string, mixed>  $counts
     */
    private function dispatchWhatsApp(array $counts): void
    {
        $body = AuditCompletionReport::whatsappBody($counts);
        $dispatched = 0;

        foreach (self::REPORT_PHONES as $rawPhone) {
            $phone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($rawPhone);

            if ($phone === null) {
                ScheduledTaskRunReport::recordFailure('Teléfono inválido para WhatsApp: '.$rawPhone);

                continue;
            }

            try {
                SendNotificacionWhatsApp::dispatch(null, $body, $phone, null, [
                    'panel' => 'system',
                    'source' => 'audit.daily-summary',
                ]);

                $dispatched++;
            } catch (Throwable $exception) {
                ScheduledTaskRunReport::recordFailure('Error al despachar WhatsApp a '.$phone);
                Log::error('SendDailyAuditSummary: error despachando WhatsApp', [
                    'phone' => $phone,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        ScheduledTaskRunReport::addMetric('WhatsApp despachados', $dispatched);
    }

    /**
     * @param  array<string, mixed>  $counts
     */
    private function sendEmail(array $counts): void
    {
        try {
            Mail::to(self::REPORT_EMAIL)->send(
                new AuditCompletionSummaryMail($counts, self::REPORT_EMAIL)
            );

            ScheduledTaskRunReport::addMetric('Email resumen enviado', 'Sí');
        } catch (Throwable $exception) {
            ScheduledTaskRunReport::recordFailure('Error al enviar email de resumen');
            ScheduledTaskRunReport::addMetric('Email resumen enviado', 'No');
            Log::error('SendDailyAuditSummary: error enviando email de resumen', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('SendDailyAuditSummary: FAILED', [
            'message' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }
}
