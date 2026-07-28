<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\IndividualQuoteFollowUpMail;
use App\Models\IndividualQuote;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\Concerns\ReportsScheduledExecution;
use App\Support\IndividualQuotes\IndividualQuoteDaySevenFollowUp;
use App\Support\IndividualQuotes\IndividualQuoteFollowUp;
use App\Support\IndividualQuotes\IndividualQuoteFollowUpInternalCopies;
use App\Support\ScheduledTaskRunReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendIndividualQuoteDaySevenFollowUp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReportsScheduledExecution, SerializesModels;

    private const FOLLOW_UP_LABEL = 'Seguimiento cotizaciones (7 días)';

    public function __construct() {}

    public function handle(): void
    {
        $this->runWithScheduledReport(
            'Seguimiento cotizaciones individuales (7 días)',
            function (): void {
                $this->dispatchFollowUpMessages();
            },
            'Envía recordatorio por WhatsApp y correo de cotizaciones individuales PRE-APROBADA creadas hace 7 días, con mensaje e imágenes informativas.',
            [
                '*Agrupación* = mismo agente o agencia y misma fecha de creación.',
                'Orden de envío WhatsApp: mensaje, imagen de adquisición del plan e imagen de métodos de pago.',
                'El mensaje se envía al teléfono y correo del agente (si hay agent_id) o de la agencia (code_agency).',
                'El correo incluye las mismas imágenes adjuntas que se envían por WhatsApp.',
                'Se envía copia interna a los destinatarios configurados en el Centro de notificaciones.',
            ],
        );
    }

    private function dispatchFollowUpMessages(): void
    {
        $groups = IndividualQuoteDaySevenFollowUp::groupedQuotesForDate();
        $quotesTotal = $groups->flatten(1)->count();
        $chainsDispatched = 0;
        $allyEmails = 0;
        $internalEmailCopies = 0;
        $internalWhatsAppCopies = 0;

        ScheduledTaskRunReport::addMetric('Cotizaciones elegibles', $quotesTotal);
        ScheduledTaskRunReport::addMetric('Grupos de aliado', $groups->count());

        foreach ($groups as $quotes) {
            /** @var Collection<int, IndividualQuote> $quotes */
            if ($quotes->isEmpty()) {
                continue;
            }

            $body = IndividualQuoteDaySevenFollowUp::whatsappBody($quotes);
            $ally = IndividualQuoteDaySevenFollowUp::resolveAllyName($quotes);
            $rawPhones = IndividualQuoteFollowUp::resolveRecipientPhones($quotes);

            if ($rawPhones === []) {
                ScheduledTaskRunReport::recordFailure(
                    'Sin teléfono de agente/agencia para el grupo '.IndividualQuoteFollowUp::groupKey($quotes->first()).' ('.$ally.')'
                );
            }

            foreach ($rawPhones as $rawPhone) {
                $phone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($rawPhone);

                if ($phone === null) {
                    ScheduledTaskRunReport::recordFailure('Teléfono inválido para WhatsApp: '.$rawPhone);

                    continue;
                }

                try {
                    $context = [
                        'panel' => 'system',
                        'source' => 'individual-quotes.day-seven-follow-up',
                        'ally' => $ally,
                        'quote_count' => $quotes->count(),
                    ];

                    Bus::chain([
                        new SendNotificacionWhatsApp(null, $body, $phone, null, $context),
                        new SendNotificacionWhatsApp(
                            null,
                            IndividualQuoteDaySevenFollowUp::planGuideImageCaption(),
                            $phone,
                            null,
                            [...$context, 'asset' => 'plan-guide'],
                            IndividualQuoteDaySevenFollowUp::planGuideImageUrl(),
                        ),
                        new SendNotificacionWhatsApp(
                            null,
                            IndividualQuoteDaySevenFollowUp::paymentMethodsImageCaption(),
                            $phone,
                            null,
                            [...$context, 'asset' => 'payment-methods'],
                            IndividualQuoteDaySevenFollowUp::paymentMethodsImageUrl(),
                        ),
                    ])->onQueue('system')->dispatch();

                    $chainsDispatched++;
                } catch (Throwable $exception) {
                    ScheduledTaskRunReport::recordFailure('Error al despachar cadena WhatsApp a '.$phone);
                    Log::error('SendIndividualQuoteDaySevenFollowUp: error despachando cadena WhatsApp', [
                        'phone' => $phone,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            $allyEmails += $this->dispatchAllyEmailMessages($quotes, $body, $ally);

            $internalCopies = IndividualQuoteFollowUpInternalCopies::dispatch(
                whatsappBody: $body,
                allyName: $ally,
                source: 'individual-quotes.day-seven-follow-up',
                followUpLabel: self::FOLLOW_UP_LABEL,
                quoteCount: $quotes->count(),
            );
            $internalEmailCopies += $internalCopies['emails'];
            $internalWhatsAppCopies += $internalCopies['whatsapps'];
        }

        ScheduledTaskRunReport::addMetric('Cadenas WhatsApp despachadas', $chainsDispatched);
        ScheduledTaskRunReport::addMetric('Correos aliado enviados', $allyEmails);
        ScheduledTaskRunReport::addMetric('Copias email internas', $internalEmailCopies);
        ScheduledTaskRunReport::addMetric('Copias WhatsApp internas', $internalWhatsAppCopies);
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
                    attachmentRelativePaths: [
                        IndividualQuoteDaySevenFollowUp::IMAGE_PLAN_GUIDE,
                        IndividualQuoteDaySevenFollowUp::IMAGE_PAYMENT_METHODS,
                    ],
                ));

                $sent++;
            } catch (Throwable $exception) {
                ScheduledTaskRunReport::recordFailure('Error al enviar correo de aliado a '.$email);
                Log::error('SendIndividualQuoteDaySevenFollowUp: error enviando correo de aliado', [
                    'email' => $email,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('SendIndividualQuoteDaySevenFollowUp: FAILED', [
            'message' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }
}
