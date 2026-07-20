<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\Concerns\ReportsScheduledExecution;
use App\Support\IndividualQuotes\IndividualQuoteDayNineFollowUp;
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

class SendIndividualQuoteDayNineFollowUp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReportsScheduledExecution, SerializesModels;

    public function __construct() {}

    public function handle(): void
    {
        $this->runWithScheduledReport(
            'Seguimiento WhatsApp cotizaciones individuales (9 días)',
            function (): void {
                $this->dispatchFollowUpMessages();
            },
            'Envía recordatorio por WhatsApp de cotizaciones individuales PRE-APROBADA creadas hace 9 días, con mensaje y flyer de beneficios.',
            [
                '*Agrupación* = mismo agente o agencia y misma fecha de creación.',
                'Orden de envío: mensaje explicativo y flyer imagenes-seguimiento-cotizaciones/flayer.pdf.',
                'El mensaje se envía al teléfono del agente (si hay agent_id) o de la agencia (code_agency).',
                'Se envía copia interna a los destinatarios configurados en el Centro de notificaciones.',
            ],
        );
    }

    private function dispatchFollowUpMessages(): void
    {
        $groups = IndividualQuoteDayNineFollowUp::groupedQuotesForDate();
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

            $body = IndividualQuoteDayNineFollowUp::whatsappBody($quotes);
            $ally = IndividualQuoteDayNineFollowUp::resolveAllyName($quotes);
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
                        'source' => 'individual-quotes.day-nine-follow-up',
                        'ally' => $ally,
                        'quote_count' => $quotes->count(),
                    ];

                    Bus::chain([
                        new SendNotificacionWhatsApp(null, $body, $phone, null, $context),
                        new SendNotificacionWhatsAppDocument(
                            null,
                            IndividualQuoteDayNineFollowUp::benefitsFlyerCaption(),
                            $phone,
                            IndividualQuoteDayNineFollowUp::benefitsFlyerUrl(),
                            IndividualQuoteDayNineFollowUp::BENEFITS_FLYER_FILENAME,
                            [...$context, 'asset' => 'benefits-flyer'],
                        ),
                    ])->onQueue('system')->dispatch();

                    $chainsDispatched++;
                } catch (Throwable $exception) {
                    ScheduledTaskRunReport::recordFailure('Error al despachar cadena WhatsApp a '.$phone);
                    Log::error('SendIndividualQuoteDayNineFollowUp: error despachando cadena WhatsApp', [
                        'phone' => $phone,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            $internalCopies = IndividualQuoteFollowUpInternalCopies::dispatch(
                whatsappBody: $body,
                allyName: $ally,
                source: 'individual-quotes.day-nine-follow-up',
                followUpLabel: 'Seguimiento cotizaciones (9 días)',
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
        Log::error('SendIndividualQuoteDayNineFollowUp: FAILED', [
            'message' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }
}
