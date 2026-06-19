<?php

declare(strict_types=1);

use App\Models\IndividualQuote;
use App\Support\IndividualQuotes\IndividualQuoteDayNineFollowUp;

uses(Tests\TestCase::class);

it('expone la url publica del flyer de beneficios en storage', function (): void {
    expect(IndividualQuoteDayNineFollowUp::benefitsFlyerUrl())
        ->toContain('/storage/imagenes-seguimiento-cotizaciones/flayer.pdf');
});

it('arma el mensaje de whatsapp de 9 dias con referencia al flyer de beneficios', function (): void {
    $quote = new IndividualQuote([
        'code' => 'COT-IND-000264',
        'full_name' => 'María García',
        'agent_id' => 1,
    ]);
    $quote->setRelation('agent', new App\Models\Agent(['name' => 'Juan Pérez']));
    $quote->created_at = now()->subDays(9);

    $body = IndividualQuoteDayNineFollowUp::whatsappBody(collect([$quote]));

    expect($body)
        ->toContain('¡Hola, *Juan Pérez*!')
        ->toContain('*María García*')
        ->toContain('flyer de beneficios de Tu Doctor en Casa')
        ->toContain('Total de cotizaciones: *1*')
        ->toContain('*COT-IND-000264*');
});

it('programa el seguimiento de cotizaciones individuales a las 8:30am', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/routes/console.php');

    expect($source)
        ->toContain('SendIndividualQuoteDayNineFollowUp')
        ->toContain("->dailyAt('8:30')")
        ->toContain('->when($individualQuoteFollowUpIsActive)');
});

it('envia el seguimiento de 9 dias en cadena con mensaje y flyer pdf', function (): void {
    $jobSource = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendIndividualQuoteDayNineFollowUp.php');

    expect($jobSource)
        ->toContain('Bus::chain')
        ->toContain('SendNotificacionWhatsAppDocument')
        ->toContain('benefitsFlyerUrl()')
        ->toContain('flayer.pdf')
        ->toContain('IndividualQuoteFollowUp::reportPhones()');
});
