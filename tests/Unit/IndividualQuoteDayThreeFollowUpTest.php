<?php

declare(strict_types=1);

use App\Models\IndividualQuote;
use App\Support\IndividualQuotes\IndividualQuoteDayThreeFollowUp;

uses(Tests\TestCase::class);

it('formatea un solo codigo de cotizacion completo', function (): void {
    $quotes = collect([
        new IndividualQuote(['code' => 'COT-IND-000264']),
    ]);

    expect(IndividualQuoteDayThreeFollowUp::formatQuoteCodes($quotes))
        ->toBe('COT-IND-000264');
});

it('formatea varios codigos de cotizacion con sufijos separados por slash', function (): void {
    $quotes = collect([
        new IndividualQuote(['code' => 'COT-IND-000264']),
        new IndividualQuote(['code' => 'COT-IND-000265']),
        new IndividualQuote(['code' => 'COT-IND-000266']),
    ]);

    expect(IndividualQuoteDayThreeFollowUp::formatQuoteCodes($quotes))
        ->toBe('COT-IND-: 000264/000265/000266');
});

it('lista los nombres de clientes en negrita separados por coma', function (): void {
    $quotes = collect([
        new IndividualQuote(['full_name' => 'María García']),
        new IndividualQuote(['full_name' => 'Pedro López']),
    ]);

    expect(IndividualQuoteDayThreeFollowUp::formatClientNames($quotes))
        ->toBe('*María García*, *Pedro López*');
});

it('arma el mensaje de whatsapp con aliado, clientes, total y footer de seguimiento', function (): void {
    $quote = new IndividualQuote([
        'code' => 'COT-IND-000264',
        'full_name' => 'María García',
        'agent_id' => 1,
        'created_at' => now()->subDays(3),
    ]);
    $quote->setRelation('agent', new App\Models\Agent(['name' => 'Juan Pérez']));

    $body = IndividualQuoteDayThreeFollowUp::whatsappBody(collect([$quote]));

    expect($body)
        ->toContain('¡Hola, *Juan Pérez*!')
        ->toContain('Tu Doctor en Casa 🩺🏡')
        ->toContain('*María García*')
        ->toContain('¿Hay alguna duda o necesitas apoyo para cerrar esta venta hoy?')
        ->toContain('¡Quedamos atentos!')
        ->toContain('*El sistema automatizado*')
        ->toContain('Total de cotizaciones: *1*')
        ->toContain('*COT-IND-000264*')
        ->toContain('Le apoya en el proceso de seguimiento de las cotizaciones generadas en la fecha indicada.');
});

it('agrupa cotizaciones por agente o por agencia', function (): void {
    $agentQuote = new IndividualQuote(['agent_id' => 10, 'code_agency' => 'AG-001']);
    $agencyQuote = new IndividualQuote(['agent_id' => null, 'code_agency' => 'AG-002']);

    expect(IndividualQuoteDayThreeFollowUp::groupKey($agentQuote))->toBe('agent:10')
        ->and(IndividualQuoteDayThreeFollowUp::groupKey($agencyQuote))->toBe('agency:AG-002');
});

it('programa el seguimiento de cotizaciones individuales a las 8:00am', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/routes/console.php');

    expect($source)
        ->toContain('SendIndividualQuoteDayThreeFollowUp')
        ->toContain("->dailyAt('8:00')")
        ->toContain('->when($individualQuoteFollowUpIsActive)');
});

it('envia el seguimiento al telefono del agente o de la agencia', function (): void {
    $jobSource = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendIndividualQuoteDayThreeFollowUp.php');
    $supportSource = file_get_contents(dirname(__DIR__, 2).'/app/Support/IndividualQuotes/IndividualQuoteFollowUp.php');

    expect($jobSource)
        ->toContain('IndividualQuoteFollowUp::resolveRecipientPhones($quotes)')
        ->toContain('IndividualQuoteFollowUpInternalCopies::dispatch')
        ->toContain('IndividualQuoteDayThreeFollowUp::whatsappBody');

    expect($supportSource)
        ->toContain("->where('status', self::ELIGIBLE_STATUS)")
        ->toContain('resolveAgentPhone')
        ->toContain('resolveAgencyPhone');
});
