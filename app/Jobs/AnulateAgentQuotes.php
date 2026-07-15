<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SystemNotificationKey;
use App\Mail\AnulatedQuotesNotificationMail;
use App\Models\IndividualQuote;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
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
use Illuminate\Support\Facades\Storage;
use Throwable;

class AnulateAgentQuotes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReportsScheduledExecution, SerializesModels;

    public function __construct() {}

    public function handle(): void
    {
        $this->runWithScheduledReport(
            'Anulación de cotizaciones de agentes',
            function (): void {
                $this->anulateAgentQuotes();
            },
            'Anula cotizaciones individuales de agentes con más de 15 días sin aprobar ni ejecutar, elimina su PDF y notifica por email/WhatsApp si hubo anulaciones.',
            [
                '*Cotizaciones anuladas* = registros que pasaron a status ANULADA.',
                '*PDFs no eliminados* = archivos que no pudieron borrarse del storage.',
                'Cada falla de PDF corresponde a una cotización concreta (1:1).',
                'Los destinatarios se gestionan en el Centro de notificaciones.',
            ],
        );
    }

    private function anulateAgentQuotes(): void
    {
        $quotes = IndividualQuote::query()
            ->whereNotIn('status', ['APROBADA', 'EJECUTADA'])
            ->where('created_at', '<=', now()->subDays(15))
            ->get();

        ScheduledTaskRunReport::addExecutionDetail('Criterio', 'Status distinto de APROBADA/EJECUTADA y creadas hace > 15 días');
        ScheduledTaskRunReport::addExecutionDetail('Candidatas encontradas', $quotes->count());

        $anulatedCount = 0;
        $pdfDeleteFailures = 0;

        foreach ($quotes as $quote) {
            $quote->update(['status' => 'ANULADA']);

            if (! $this->deleteQuotePdf($quote->code)) {
                $pdfDeleteFailures++;
                ScheduledTaskRunReport::recordFailure('Error al eliminar PDF de cotización');
            }

            $anulatedCount++;
        }

        ScheduledTaskRunReport::addMetric('Cotizaciones anuladas', $anulatedCount);
        ScheduledTaskRunReport::addMetric('PDFs no eliminados', $pdfDeleteFailures);

        if ($anulatedCount > 0) {
            $this->notifyRecipients($anulatedCount);
        } else {
            ScheduledTaskRunReport::addMetric('Email resumen enviado', 'No aplica');
            ScheduledTaskRunReport::addMetric('WhatsApp resumen despachados', 'No aplica');
        }
    }

    private function notifyRecipients(int $anulatedCount): void
    {
        $emails = SystemNotificationRecipients::emails(SystemNotificationKey::AgentQuoteAnulation);
        $phones = SystemNotificationRecipients::phones(SystemNotificationKey::AgentQuoteAnulation);

        if ($emails === [] && $phones === []) {
            ScheduledTaskRunReport::addMetric('Email resumen enviado', 'Sin destinatarios');
            ScheduledTaskRunReport::addMetric('WhatsApp resumen despachados', 'Sin destinatarios');
            ScheduledTaskRunReport::recordFailure('Hubo anulaciones pero no hay destinatarios configurados en el Centro de notificaciones');

            return;
        }

        $emailsSent = 0;
        $whatsappsQueued = 0;

        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                ScheduledTaskRunReport::recordFailure('Correo inválido para resumen de anulación: '.$email);

                continue;
            }

            try {
                Mail::to($email)->send(new AnulatedQuotesNotificationMail($anulatedCount, $email));
                $emailsSent++;
            } catch (Throwable $exception) {
                ScheduledTaskRunReport::recordFailure('Error al enviar email de resumen a '.$email);
                Log::error('AnulateAgentQuotes: error enviando email de resumen', [
                    'email' => $email,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $whatsappBody = <<<TEXT
        *INTEGRACORP · Cotizaciones anuladas*

        Reporte diario de cotizaciones individuales anuladas automáticamente
        (más de 15 días sin aprobar ni ejecutar).

        Número de cotizaciones anuladas: *{$anulatedCount}*
        TEXT;

        foreach ($phones as $rawPhone) {
            $phone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($rawPhone);

            if ($phone === null) {
                ScheduledTaskRunReport::recordFailure('Teléfono inválido para resumen de anulación: '.$rawPhone);

                continue;
            }

            try {
                SendNotificacionWhatsApp::dispatch(null, $whatsappBody, $phone, null, [
                    'panel' => 'system',
                    'source' => 'individual-quotes.agent-quote-anulation',
                    'anulated_count' => $anulatedCount,
                ]);
                $whatsappsQueued++;
            } catch (Throwable $exception) {
                ScheduledTaskRunReport::recordFailure('Error al despachar WhatsApp de resumen a '.$phone);
                Log::error('AnulateAgentQuotes: error despachando WhatsApp de resumen', [
                    'phone' => $phone,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        ScheduledTaskRunReport::addMetric('Email resumen enviado', $emailsSent > 0 ? 'Sí ('.$emailsSent.')' : 'No');
        ScheduledTaskRunReport::addMetric('WhatsApp resumen despachados', $whatsappsQueued);
    }

    private function deleteQuotePdf(string $code): bool
    {
        $filename = $code.'.pdf';
        $deleted = true;

        $publicPath = public_path('storage/quotes/'.$filename);
        if (file_exists($publicPath) && ! unlink($publicPath)) {
            $deleted = false;
        }

        if (Storage::disk('public')->exists('quotes/'.$filename) && ! Storage::disk('public')->delete('quotes/'.$filename)) {
            $deleted = false;
        }

        return $deleted;
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('AnulateAgentQuotes: FAILED', [
            'message' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }
}
