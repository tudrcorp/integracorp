<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendNotificacionWhatsApp;
use App\Jobs\SendNotificacionWhatsAppDocument;
use App\Jobs\SendNotificacionWhatsAppVideo;
use App\Models\Agent;
use App\Models\IndividualQuote;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\IndividualQuotes\IndividualQuoteDayFiveFollowUp;
use App\Support\IndividualQuotes\IndividualQuoteDayNineFollowUp;
use App\Support\IndividualQuotes\IndividualQuoteDaySevenFollowUp;
use App\Support\IndividualQuotes\IndividualQuoteDayThreeFollowUp;
use App\Support\IndividualQuotes\IndividualQuoteDayTwelveFollowUp;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;

class SendIndividualQuoteFollowUpWhatsAppTest extends Command
{
    protected $signature = 'individual-quotes:test-follow-up-whatsapp
        {phone : Teléfono destino, ej. 04127018390}
        {--days=3 : Días de seguimiento (3, 5, 7, 9 o 12)}
        {--date= : Fecha de creación a simular (Y-m-d). Por defecto: hoy}
        {--sample : Usa datos de ejemplo aunque existan cotizaciones reales}
        {--preview : Solo muestra el mensaje sin enviar WhatsApp}
        {--queue : Encola el envío en lugar de enviarlo de inmediato}';

    protected $description = 'Previsualiza y envía el seguimiento de cotizaciones individuales (3, 5, 7, 9 o 12 días) por WhatsApp a un teléfono de prueba.';

    public function handle(): int
    {
        $rawPhone = (string) $this->argument('phone');
        $phone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($rawPhone);

        if ($phone === null) {
            $this->error("Teléfono inválido: {$rawPhone}");

            return self::FAILURE;
        }

        $followUpDays = (int) $this->option('days');

        if (! in_array($followUpDays, [3, 5, 7, 9, 12], true)) {
            $this->error('El parámetro --days solo admite 3, 5, 7, 9 o 12.');

            return self::FAILURE;
        }

        $referenceDate = $this->resolveReferenceDate();
        $groups = $this->option('sample')
            ? collect([$this->sampleQuotesGroup($followUpDays)])
            : $this->resolveGroups($followUpDays, $referenceDate);

        if ($groups->isEmpty()) {
            $this->warn('No hay cotizaciones PRE-APROBADA para la fecha indicada. Usando datos de ejemplo.');
            $groups = collect([$this->sampleQuotesGroup($followUpDays)]);
        }

        $this->info('Seguimiento de '.$followUpDays.' días');
        $this->info('Fecha de creación evaluada: '.$referenceDate->copy()->subDays($followUpDays)->format('d/m/Y'));
        $this->info('Grupos encontrados: '.$groups->count());
        $this->newLine();

        foreach ($groups->values() as $index => $quotes) {
            /** @var Collection<int, IndividualQuote> $quotes */
            $body = match ($followUpDays) {
                5 => IndividualQuoteDayFiveFollowUp::whatsappBody($quotes),
                7 => IndividualQuoteDaySevenFollowUp::whatsappBody($quotes),
                9 => IndividualQuoteDayNineFollowUp::whatsappBody($quotes),
                12 => IndividualQuoteDayTwelveFollowUp::whatsappBody($quotes),
                default => IndividualQuoteDayThreeFollowUp::whatsappBody($quotes),
            };
            $ally = match ($followUpDays) {
                5 => IndividualQuoteDayFiveFollowUp::resolveAllyName($quotes),
                7 => IndividualQuoteDaySevenFollowUp::resolveAllyName($quotes),
                9 => IndividualQuoteDayNineFollowUp::resolveAllyName($quotes),
                12 => IndividualQuoteDayTwelveFollowUp::resolveAllyName($quotes),
                default => IndividualQuoteDayThreeFollowUp::resolveAllyName($quotes),
            };

            $this->line(str_repeat('─', 60));
            $this->info('Grupo '.($index + 1).' — Aliado: '.$ally.' ('.$quotes->count().' cotización/es)');
            $this->newLine();
            $this->line($body);
            $this->newLine();

            if ($followUpDays === 5) {
                $this->comment('Video: '.IndividualQuoteDayFiveFollowUp::followUpVideoUrl());
                $this->newLine();
            }

            if ($followUpDays === 7) {
                $this->comment('Imagen 1: '.IndividualQuoteDaySevenFollowUp::planGuideImageUrl());
                $this->comment('Imagen 2: '.IndividualQuoteDaySevenFollowUp::paymentMethodsImageUrl());
                $this->newLine();
            }

            if ($followUpDays === 9) {
                $this->comment('Flyer: '.IndividualQuoteDayNineFollowUp::benefitsFlyerUrl());
                $this->newLine();
            }

            if ($this->option('preview')) {
                continue;
            }

            $context = [
                'panel' => 'system',
                'source' => 'individual-quotes.day-'.$followUpDays.'-follow-up.test',
                'ally' => $ally,
                'quote_count' => $quotes->count(),
            ];

            if ($followUpDays === 5) {
                $this->dispatchDayFiveMessages($phone, $body, $context, $index + 1);

                continue;
            }

            if ($followUpDays === 7) {
                $this->dispatchDaySevenMessages($phone, $body, $context, $index + 1);

                continue;
            }

            if ($followUpDays === 9) {
                $this->dispatchDayNineMessages($phone, $body, $context, $index + 1);

                continue;
            }

            if ($this->option('queue')) {
                SendNotificacionWhatsApp::dispatch(null, $body, $phone, null, $context);
                $this->info('WhatsApp del grupo '.($index + 1)." encolado para {$phone}.");

                continue;
            }

            SendNotificacionWhatsApp::dispatchSync(null, $body, $phone, null, $context);
            $this->info('WhatsApp del grupo '.($index + 1)." enviado a {$phone}.");
        }

        if ($this->option('preview')) {
            $this->comment('Modo vista previa: no se envió ningún WhatsApp.');
        }

        return self::SUCCESS;
    }

    private function resolveReferenceDate(): Carbon
    {
        $dateOption = $this->option('date');

        if (is_string($dateOption) && $dateOption !== '') {
            return Carbon::parse($dateOption, (string) config('app.timezone'));
        }

        return now()->timezone((string) config('app.timezone'));
    }

    /**
     * @return Collection<int, Collection<int, IndividualQuote>>
     */
    private function resolveGroups(int $followUpDays, Carbon $referenceDate): Collection
    {
        return match ($followUpDays) {
            5 => IndividualQuoteDayFiveFollowUp::groupedQuotesForDate($referenceDate),
            7 => IndividualQuoteDaySevenFollowUp::groupedQuotesForDate($referenceDate),
            9 => IndividualQuoteDayNineFollowUp::groupedQuotesForDate($referenceDate),
            12 => IndividualQuoteDayTwelveFollowUp::groupedQuotesForDate($referenceDate),
            default => IndividualQuoteDayThreeFollowUp::groupedQuotesForDate($referenceDate),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function dispatchDayFiveMessages(string $phone, string $body, array $context, int $groupNumber): void
    {
        $chain = [
            new SendNotificacionWhatsApp(null, $body, $phone, null, $context),
            new SendNotificacionWhatsAppVideo(
                null,
                IndividualQuoteDayFiveFollowUp::followUpVideoCaption(),
                $phone,
                IndividualQuoteDayFiveFollowUp::followUpVideoUrl(),
                [...$context, 'asset' => 'follow-up-video'],
            ),
        ];

        if ($this->option('queue')) {
            Bus::chain($chain)->onQueue('system')->dispatch();
            $this->info('Cadena WhatsApp del grupo '.$groupNumber." encolada para {$phone}.");

            return;
        }

        Bus::chain($chain)->onConnection('sync')->dispatch();
        $this->info('Cadena WhatsApp del grupo '.$groupNumber." enviada a {$phone} (mensaje + video).");
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function dispatchDaySevenMessages(string $phone, string $body, array $context, int $groupNumber): void
    {
        $chain = [
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
        ];

        if ($this->option('queue')) {
            Bus::chain($chain)->onQueue('system')->dispatch();
            $this->info('Cadena WhatsApp del grupo '.$groupNumber." encolada para {$phone}.");

            return;
        }

        Bus::chain($chain)->onConnection('sync')->dispatch();
        $this->info('Cadena WhatsApp del grupo '.$groupNumber." enviada a {$phone} (mensaje + 2 imágenes).");
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function dispatchDayNineMessages(string $phone, string $body, array $context, int $groupNumber): void
    {
        $chain = [
            new SendNotificacionWhatsApp(null, $body, $phone, null, $context),
            new SendNotificacionWhatsAppDocument(
                null,
                IndividualQuoteDayNineFollowUp::benefitsFlyerCaption(),
                $phone,
                IndividualQuoteDayNineFollowUp::benefitsFlyerUrl(),
                IndividualQuoteDayNineFollowUp::BENEFITS_FLYER_FILENAME,
                [...$context, 'asset' => 'benefits-flyer'],
            ),
        ];

        if ($this->option('queue')) {
            Bus::chain($chain)->onQueue('system')->dispatch();
            $this->info('Cadena WhatsApp del grupo '.$groupNumber." encolada para {$phone}.");

            return;
        }

        Bus::chain($chain)->onConnection('sync')->dispatch();
        $this->info('Cadena WhatsApp del grupo '.$groupNumber." enviada a {$phone} (mensaje + flyer PDF).");
    }

    /**
     * @return Collection<int, IndividualQuote>
     */
    private function sampleQuotesGroup(int $followUpDays): Collection
    {
        $createdAt = now()->subDays($followUpDays);
        $eligibleStatus = IndividualQuoteDayThreeFollowUp::ELIGIBLE_STATUS;

        $agent = new Agent(['name' => 'Juan Pérez Agente']);

        $quoteOne = new IndividualQuote([
            'code' => 'COT-IND-000264',
            'full_name' => 'María García López',
            'agent_id' => 1,
            'status' => $eligibleStatus,
        ]);
        $quoteOne->setRelation('agent', $agent);
        $quoteOne->created_at = $createdAt;

        $quoteTwo = new IndividualQuote([
            'code' => 'COT-IND-000265',
            'full_name' => 'Pedro Rodríguez',
            'agent_id' => 1,
            'status' => $eligibleStatus,
        ]);
        $quoteTwo->setRelation('agent', $agent);
        $quoteTwo->created_at = $createdAt;

        return collect([$quoteOne, $quoteTwo]);
    }
}
