<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SystemNotificationKey;
use App\Mail\AuditCompletionSummaryMail;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\Audit\AuditCompletionReport;
use App\Support\Concerns\ReportsScheduledExecution;
use App\Support\ScheduledTaskRunReport;
use App\Support\SystemNotificationRecipients;
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
                'Los destinatarios se gestionan en el Centro de notificaciones (Auditorías completas).',
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

        $emails = SystemNotificationRecipients::emails(SystemNotificationKey::DailyAuditSummary);
        $phones = SystemNotificationRecipients::phones(SystemNotificationKey::DailyAuditSummary);

        if ($emails === [] && $phones === []) {
            ScheduledTaskRunReport::addMetric('WhatsApp despachados', 0);
            ScheduledTaskRunReport::addMetric('Email resumen enviado', 'Sin destinatarios');
            ScheduledTaskRunReport::recordFailure('No hay destinatarios configurados en el Centro de notificaciones (Auditorías completas)');

            return;
        }

        $this->dispatchWhatsApp($counts, $phones);
        $this->sendEmails($counts, $emails);
    }

    /**
     * @param  array<string, mixed>  $counts
     * @param  list<string>  $phones
     */
    private function dispatchWhatsApp(array $counts, array $phones): void
    {
        $body = AuditCompletionReport::whatsappBody($counts);
        $dispatched = 0;

        foreach ($phones as $rawPhone) {
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
     * @param  list<string>  $emails
     */
    private function sendEmails(array $counts, array $emails): void
    {
        $emailsSent = 0;

        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                ScheduledTaskRunReport::recordFailure('Correo inválido para resumen de auditoría: '.$email);

                continue;
            }

            try {
                Mail::to($email)->send(new AuditCompletionSummaryMail($counts, $email));
                $emailsSent++;
            } catch (Throwable $exception) {
                ScheduledTaskRunReport::recordFailure('Error al enviar email de resumen a '.$email);
                Log::error('SendDailyAuditSummary: error enviando email de resumen', [
                    'email' => $email,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        ScheduledTaskRunReport::addMetric(
            'Email resumen enviado',
            $emailsSent > 0 ? 'Sí ('.$emailsSent.')' : ($emails === [] ? 'No aplica' : 'No'),
        );
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('SendDailyAuditSummary: FAILED', [
            'message' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }
}
