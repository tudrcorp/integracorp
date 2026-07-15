<?php

declare(strict_types=1);

namespace App\Jobs;

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
use Throwable;

class SendIndividualQuoteDaySevenFollowUp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReportsScheduledExecution, SerializesModels;

    public function __construct() {}

    public function handle(): void
    {
        $this->runWithScheduledReport(
            'Seguimiento WhatsApp cotizaciones individuales (7 días)',
            function (): void {
                $this->dispatchFollowUpMessages();
            },
            'Envía recordatorio por WhatsApp de cotizaciones individuales PRE-APROBADA creadas hace 7 días, con mensaje e imágenes informativas.',
            [
                '*Agrupación* = mismo agente o agencia y misma fecha de creación.',
                'Orden de envío: mensaje, imagen de adquisición del plan e imagen de métodos de pago.',
                'El mensaje se envía al teléfono del agente (si hay agent_id) o de la agencia (code_agency).',
                'Se envía copia interna a los destinatarios configurados en el Centro de notificaciones.',
            ],
        );
    }

    private function dispatchFollowUpMessages(): void
    {
        $groups = IndividualQuoteDaySevenFollowUp::groupedQuotesForDate();
        $quotesTotal = $groups->flatten(1)->count();
        $chainsDispatched = 0;
        $internalEmailCopies = 0;
        $internalWhatsAppCopies = 0;

        ScheduledTaskRunReport::addMetric('Cotizaciones elegibles', $quotesTotal);
        ScheduledTaskRunReport::addMetric('Grupos de aliado', $groups->count());

        foreach ($groups as $quotes) {
            /** @var Collection<int, \App\Models\IndividualQuote> $quotes */
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

                continue;
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

            $internalCopies = IndividualQuoteFollowUpInternalCopies::dispatch(
                whatsappBody: $body,
                allyName: $ally,
                source: 'individual-quotes.day-seven-follow-up',
                followUpLabel: 'Seguimiento cotizaciones (7 días)',
                quoteCount: $quotes->count(),
            );
            $internalEmailCopies += $internalCopies['emails'];
            $internalWhatsAppCopies += $internalCopies['whatsapps'];
        }

        ScheduledTaskRunReport::addMetric('Cadenas WhatsApp despachadas', $chainsDispatched);
        ScheduledTaskRunReport::addMetric('Copias email internas', $internalEmailCopies);
        ScheduledTaskRunReport::addMetric('Copias WhatsApp internas', $internalWhatsAppCopies);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('SendIndividualQuoteDaySevenFollowUp: FAILED', [
            'message' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }
}
