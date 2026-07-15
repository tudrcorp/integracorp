<?php

declare(strict_types=1);

use App\Models\IndividualQuote;
use App\Support\IndividualQuotes\IndividualQuoteDaySevenFollowUp;

uses(Tests\TestCase::class);

it('expone las urls publicas de las imagenes de seguimiento de 7 dias', function (): void {
    expect(IndividualQuoteDaySevenFollowUp::planGuideImageUrl())
        ->toContain('/storage/imagenes-seguimiento-cotizaciones/img1.png')
        ->and(IndividualQuoteDaySevenFollowUp::paymentMethodsImageUrl())
        ->toContain('/storage/imagenes-seguimiento-cotizaciones/img2.png');
});

it('arma el mensaje de whatsapp de 7 dias con referencia a las dos imagenes', function (): void {
    $quote = new IndividualQuote([
        'code' => 'COT-IND-000264',
        'full_name' => 'María García',
        'agent_id' => 1,
    ]);
    $quote->setRelation('agent', new App\Models\Agent(['name' => 'Juan Pérez']));
    $quote->created_at = now()->subDays(7);

    $body = IndividualQuoteDaySevenFollowUp::whatsappBody(collect([$quote]));

    expect($body)
        ->toContain('¡Hola, *Juan Pérez*!')
        ->toContain('*María García*')
        ->toContain('dos imágenes')
        ->toContain('cómo adquirir el plan')
        ->toContain('métodos de pago')
        ->toContain('Total de cotizaciones: *1*')
        ->toContain('*COT-IND-000264*');
});

it('programa el seguimiento de cotizaciones individuales a las 8:20am', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/routes/console.php');

    expect($source)
        ->toContain('SendIndividualQuoteDaySevenFollowUp')
        ->toContain("->dailyAt('8:20')")
        ->toContain('->when($individualQuoteFollowUpIsActive)');
});

it('envia el seguimiento de 7 dias en cadena con mensaje e imagenes', function (): void {
    $jobSource = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendIndividualQuoteDaySevenFollowUp.php');

    expect($jobSource)
        ->toContain('Bus::chain')
        ->toContain('planGuideImageUrl()')
        ->toContain('paymentMethodsImageUrl()')
        ->toContain('IndividualQuoteFollowUp::resolveRecipientPhones($quotes)')
        ->toContain('IndividualQuoteFollowUpInternalCopies::dispatch');
});
