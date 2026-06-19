<?php

declare(strict_types=1);

use App\Models\IndividualQuote;
use App\Support\IndividualQuotes\IndividualQuoteDayTwelveFollowUp;

uses(Tests\TestCase::class);

it('arma el mensaje de whatsapp de 12 dias con recordatorio de vencimiento', function (): void {
    $quote = new IndividualQuote([
        'code' => 'COT-IND-000264',
        'full_name' => 'María García',
        'agent_id' => 1,
    ]);
    $quote->setRelation('agent', new App\Models\Agent(['name' => 'Juan Pérez']));
    $quote->created_at = now()->subDays(12);

    $body = IndividualQuoteDayTwelveFollowUp::whatsappBody(collect([$quote]));

    expect($body)
        ->toContain('¡Hola, *Juan Pérez*! 😊')
        ->toContain('Tu Doctor en Casa 🩺 🏡')
        ->toContain('*María García*')
        ->toContain('su cotización vence pronto')
        ->toContain('propuesta más flexible o a la medida')
        ->toContain('Total de cotizaciones: *1*')
        ->toContain('*COT-IND-000264*');
});

it('programa el seguimiento de cotizaciones individuales a las 8:40am', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/routes/console.php');

    expect($source)
        ->toContain('SendIndividualQuoteDayTwelveFollowUp')
        ->toContain("->dailyAt('8:40')")
        ->toContain('->when($individualQuoteFollowUpIsActive)');
});

it('envia el seguimiento de 12 dias a los telefonos internos indicados', function (): void {
    $jobSource = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendIndividualQuoteDayTwelveFollowUp.php');

    expect($jobSource)
        ->toContain('IndividualQuoteFollowUp::reportPhones()')
        ->toContain('IndividualQuoteDayTwelveFollowUp::whatsappBody');
});
