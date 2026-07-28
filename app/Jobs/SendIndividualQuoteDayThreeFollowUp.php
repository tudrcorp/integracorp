<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\IndividualQuoteFollowUpMail;
use App\Models\IndividualQuote;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\Concerns\ReportsScheduledExecution;
use App\Support\IndividualQuotes\IndividualQuoteDayThreeFollowUp;
use App\Support\IndividualQuotes\IndividualQuoteFollowUp;
use App\Support\IndividualQuotes\IndividualQuoteFollowUpInternalCopies;
use App\Support\ScheduledTaskRunReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendIndividualQuoteDayThreeFollowUp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReportsScheduledExecution, SerializesModels;

    private const FOLLOW_UP_LABEL = 'Seguimiento cotizaciones (3 días)';

    public function __construct() {}

    public function handle(): void
    {
        $this->runWithScheduledReport(
            'Seguimiento cotizaciones individuales (3 días)',
            function (): void {
                $this->dispatchFollowUpMessages();
            },
            'Envía recordatorio por WhatsApp y correo de cotizaciones individuales PRE-APROBADA creadas hace 3 días, agrupadas por agente o agencia.',
            [
                '*Agrupación* = mismo agente o agencia y misma fecha de creación.',
                'El mensaje se envía al teléfono y correo del agente (si hay agent_id) o de la agencia (code_agency).',
                'Además se envía un mensaje directo al teléfono y correo del cliente cotizado.',
                'Se envía copia interna a los destinatarios configurados en el Centro de notificaciones.',
            ],
        );
    }

    private function dispatchFollowUpMessages(): void
    {
        $groups = IndividualQuoteDayThreeFollowUp::groupedQuotesForDate();
        $quotesTotal = $groups->flatten(1)->count();
        $allyWhatsApps = 0;
        $allyEmails = 0;
        $clientWhatsApps = 0;
        $clientEmails = 0;
        $internalEmailCopies = 0;
        $internalWhatsAppCopies = 0;

        ScheduledTaskRunReport::addMetric('Cotizaciones elegibles', $quotesTotal);
        ScheduledTaskRunReport::addMetric('Grupos de aliado', $groups->count());

        foreach ($groups as $quotes) {
            /** @var Collection<int, IndividualQuote> $quotes */
            if ($quotes->isEmpty()) {
                continue;
            }

            $body = IndividualQuoteDayThreeFollowUp::whatsappBody($quotes);
            $ally = IndividualQuoteDayThreeFollowUp::resolveAllyName($quotes);

            $allyWhatsApps += $this->dispatchAllyWhatsAppMessages($quotes, $body, $ally);
            $allyEmails += $this->dispatchAllyEmailMessages($quotes, $body, $ally);

            $clientCounts = $this->dispatchClientFollowUpMessages($quotes, $ally);
            $clientWhatsApps += $clientCounts['whatsapps'];
            $clientEmails += $clientCounts['emails'];

            $internalCopies = IndividualQuoteFollowUpInternalCopies::dispatch(
                whatsappBody: $body,
                allyName: $ally,
                source: 'individual-quotes.day-three-follow-up',
                followUpLabel: self::FOLLOW_UP_LABEL,
                quoteCount: $quotes->count(),
            );
            $internalEmailCopies += $internalCopies['emails'];
            $internalWhatsAppCopies += $internalCopies['whatsapps'];
        }

        ScheduledTaskRunReport::addMetric('WhatsApp aliado despachados', $allyWhatsApps);
        ScheduledTaskRunReport::addMetric('Correos aliado enviados', $allyEmails);
        ScheduledTaskRunReport::addMetric('WhatsApp cliente despachados', $clientWhatsApps);
        ScheduledTaskRunReport::addMetric('Correos cliente enviados', $clientEmails);
        ScheduledTaskRunReport::addMetric('Copias email internas', $internalEmailCopies);
        ScheduledTaskRunReport::addMetric('Copias WhatsApp internas', $internalWhatsAppCopies);
    }

    /**
     * @param  Collection<int, IndividualQuote>  $quotes
     */
    private function dispatchAllyWhatsAppMessages(Collection $quotes, string $body, string $ally): int
    {
        $dispatched = 0;
        $rawPhones = IndividualQuoteFollowUp::resolveRecipientPhones($quotes);

        if ($rawPhones === []) {
            ScheduledTaskRunReport::recordFailure(
                'Sin teléfono de agente/agencia para el grupo '.IndividualQuoteFollowUp::groupKey($quotes->first()).' ('.$ally.')'
            );

            return 0;
        }

        foreach ($rawPhones as $rawPhone) {
            $phone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($rawPhone);

            if ($phone === null) {
                ScheduledTaskRunReport::recordFailure('Teléfono inválido para WhatsApp: '.$rawPhone);

                continue;
            }

            try {
                SendNotificacionWhatsApp::dispatch(null, $body, $phone, null, [
                    'panel' => 'system',
                    'source' => 'individual-quotes.day-three-follow-up',
                    'ally' => $ally,
                    'quote_count' => $quotes->count(),
                ]);

                $dispatched++;
            } catch (Throwable $exception) {
                ScheduledTaskRunReport::recordFailure('Error al despachar WhatsApp a '.$phone);
                Log::error('SendIndividualQuoteDayThreeFollowUp: error despachando WhatsApp', [
                    'phone' => $phone,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $dispatched;
    }

    /**
     * @param  Collection<int, IndividualQuote>  $quotes
     */
    private function dispatchAllyEmailMessages(Collection $quotes, string $body, string $ally): int
    {
        $sent = 0;
        $emails = IndividualQuoteFollowUp::resolveRecipientEmails($quotes);

        if ($emails === []) {
            ScheduledTaskRunReport::recordFailure(
                'Sin correo de agente/agencia para el grupo '.IndividualQuoteFollowUp::groupKey($quotes->first()).' ('.$ally.')'
            );

            return 0;
        }

        foreach ($emails as $email) {
            try {
                Mail::to($email)->send(new IndividualQuoteFollowUpMail(
                    recipientEmail: $email,
                    recipientName: $ally,
                    subjectLine: self::FOLLOW_UP_LABEL.' · Tu Doctor en Casa',
                    followUpLabel: self::FOLLOW_UP_LABEL,
                    messageBody: $body,
                    audienceLabel: 'te compartimos el seguimiento de las cotizaciones asociadas a tu gestión:',
                ));

                $sent++;
            } catch (Throwable $exception) {
                ScheduledTaskRunReport::recordFailure('Error al enviar correo de aliado a '.$email);
                Log::error('SendIndividualQuoteDayThreeFollowUp: error enviando correo de aliado', [
                    'email' => $email,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * @param  Collection<int, IndividualQuote>  $quotes
     * @return array{whatsapps: int, emails: int}
     */
    private function dispatchClientFollowUpMessages(Collection $quotes, string $ally): array
    {
        $whatsapps = 0;
        $emails = 0;

        foreach ($quotes as $quote) {
            $clientBody = IndividualQuoteDayThreeFollowUp::clientWhatsappBody($quote);
            $clientName = filled($quote->full_name)
                ? (string) $quote->full_name
                : 'Cliente';

            $whatsapps += $this->dispatchClientWhatsApp($quote, $clientBody, $ally, $clientName);
            $emails += $this->dispatchClientEmail($quote, $clientBody, $clientName);
        }

        return [
            'whatsapps' => $whatsapps,
            'emails' => $emails,
        ];
    }

    private function dispatchClientWhatsApp(
        IndividualQuote $quote,
        string $clientBody,
        string $ally,
        string $clientName,
    ): int {
        $rawPhone = trim((string) $quote->phone);

        if ($rawPhone === '') {
            ScheduledTaskRunReport::recordFailure(
                'Sin teléfono de cliente para cotización '.(string) ($quote->code ?? $quote->getKey())
            );

            return 0;
        }

        $phone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($rawPhone);

        if ($phone === null) {
            ScheduledTaskRunReport::recordFailure('Teléfono de cliente inválido para WhatsApp: '.$rawPhone);

            return 0;
        }

        try {
            SendNotificacionWhatsApp::dispatch(null, $clientBody, $phone, null, [
                'panel' => 'system',
                'source' => 'individual-quotes.day-three-follow-up.client',
                'ally' => $ally,
                'quote_code' => $quote->code,
                'client_name' => $clientName,
            ]);

            return 1;
        } catch (Throwable $exception) {
            ScheduledTaskRunReport::recordFailure('Error al despachar WhatsApp de cliente a '.$phone);
            Log::error('SendIndividualQuoteDayThreeFollowUp: error despachando WhatsApp de cliente', [
                'phone' => $phone,
                'quote_code' => $quote->code,
                'message' => $exception->getMessage(),
            ]);

            return 0;
        }
    }

    private function dispatchClientEmail(
        IndividualQuote $quote,
        string $clientBody,
        string $clientName,
    ): int {
        $email = trim((string) $quote->email);

        if ($email === '') {
            ScheduledTaskRunReport::recordFailure(
                'Sin correo de cliente para cotización '.(string) ($quote->code ?? $quote->getKey())
            );

            return 0;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ScheduledTaskRunReport::recordFailure('Correo de cliente inválido: '.$email);

            return 0;
        }

        try {
            Mail::to($email)->send(new IndividualQuoteFollowUpMail(
                recipientEmail: $email,
                recipientName: $clientName,
                subjectLine: 'Seguimiento de tu cotización · Tu Doctor en Casa',
                followUpLabel: self::FOLLOW_UP_LABEL,
                messageBody: $clientBody,
                audienceLabel: 'te saludamos de Tu Doctor en Casa con el seguimiento de tu cotización:',
            ));

            return 1;
        } catch (Throwable $exception) {
            ScheduledTaskRunReport::recordFailure('Error al enviar correo de cliente a '.$email);
            Log::error('SendIndividualQuoteDayThreeFollowUp: error enviando correo de cliente', [
                'email' => $email,
                'quote_code' => $quote->code,
                'message' => $exception->getMessage(),
            ]);

            return 0;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('SendIndividualQuoteDayThreeFollowUp: FAILED', [
            'message' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }
}
