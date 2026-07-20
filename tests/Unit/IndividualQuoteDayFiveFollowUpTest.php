<?php

declare(strict_types=1);

use App\Models\IndividualQuote;
use App\Support\IndividualQuotes\IndividualQuoteDayFiveFollowUp;

uses(Tests\TestCase::class);

it('formatea un solo codigo de cotizacion completo en seguimiento de 5 dias', function (): void {
    $quotes = collect([
        new IndividualQuote(['code' => 'COT-IND-000264']),
    ]);

    expect(IndividualQuoteDayFiveFollowUp::formatQuoteCodes($quotes))
        ->toBe('COT-IND-000264');
});

it('formatea varios codigos de cotizacion con sufijos separados por slash en seguimiento de 5 dias', function (): void {
    $quotes = collect([
        new IndividualQuote(['code' => 'COT-IND-000264']),
        new IndividualQuote(['code' => 'COT-IND-000265']),
        new IndividualQuote(['code' => 'COT-IND-000266']),
    ]);

    expect(IndividualQuoteDayFiveFollowUp::formatQuoteCodes($quotes))
        ->toBe('COT-IND-: 000264/000265/000266');
});

it('arma el mensaje de whatsapp de 5 dias con referencia al video informativo', function (): void {
    $quote = new IndividualQuote([
        'code' => 'COT-IND-000264',
        'full_name' => 'María García',
        'agent_id' => 1,
    ]);
    $quote->setRelation('agent', new App\Models\Agent(['name' => 'Juan Pérez']));
    $quote->created_at = now()->subDays(5);

    $body = IndividualQuoteDayFiveFollowUp::whatsappBody(collect([$quote]));

    expect($body)
        ->toContain('¡Hola, *Juan Pérez*!')
        ->toContain('*María García*')
        ->toContain('video que explica por qué deben elegirnos')
        ->toContain('Tu Doctor en Casa 🩺🏡')
        ->toContain('Total de cotizaciones: *1*')
        ->toContain('*COT-IND-000264*')
        ->toContain('Le apoya en el proceso de seguimiento');
});

it('expone la url publica del video de seguimiento en storage', function (): void {
    expect(IndividualQuoteDayFiveFollowUp::followUpVideoUrl())
        ->toContain('/storage/imagenes-seguimiento-cotizaciones/video-mensaje-dos.mp4');
});

it('programa el seguimiento de cotizaciones individuales a las 8:10am', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/routes/console.php');

    expect($source)
        ->toContain('SendIndividualQuoteDayFiveFollowUp')
        ->toContain("->dailyAt('8:10')")
        ->toContain('->when($individualQuoteFollowUpIsActive)');
});

it('envia el seguimiento de 5 dias en cadena con mensaje y video', function (): void {
    $jobSource = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendIndividualQuoteDayFiveFollowUp.php');

    expect($jobSource)
        ->toContain('Bus::chain')
        ->toContain('SendNotificacionWhatsAppVideo')
        ->toContain('followUpVideoUrl()')
        ->toContain('IndividualQuoteFollowUp::resolveRecipientPhones($quotes)')
        ->toContain('IndividualQuoteFollowUpInternalCopies::dispatch');
});
