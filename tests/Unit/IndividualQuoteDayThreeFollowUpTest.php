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

it('arma el mensaje de whatsapp directo al cliente cotizado', function (): void {
    $quote = new IndividualQuote([
        'code' => 'COT-IND-000264',
        'full_name' => 'María García',
        'phone' => '04121234567',
    ]);

    $body = IndividualQuoteDayThreeFollowUp::clientWhatsappBody($quote);

    expect($body)
        ->toContain('¡Hola, *María García*!')
        ->toContain('Tu Doctor en Casa 🩺🏡')
        ->toContain('Vimos que cotizaste con nosotros')
        ->toContain('¿Tienes alguna duda para concretar tu compra?')
        ->toContain('¡Quedo atento para ayudarte!')
        ->not->toContain('*El sistema automatizado*');
});

it('usa Cliente como nombre por defecto si la cotizacion no tiene full_name', function (): void {
    $quote = new IndividualQuote([
        'code' => 'COT-IND-000264',
        'full_name' => null,
    ]);

    expect(IndividualQuoteDayThreeFollowUp::clientWhatsappBody($quote))
        ->toContain('¡Hola, *Cliente*!');
});

it('resuelve el correo del agente o de la agencia para el seguimiento', function (): void {
    $agentQuote = new IndividualQuote(['agent_id' => 1]);
    $agentQuote->setRelation('agent', new App\Models\Agent([
        'name' => 'Juan Pérez',
        'email' => 'agente@example.com',
    ]));

    $agencyQuote = new IndividualQuote(['agent_id' => null, 'code_agency' => 'AG-001']);
    $agencyQuote->setRelation('agency', new App\Models\Agency([
        'code' => 'AG-001',
        'name_corporative' => 'Agencia Demo',
        'email' => 'agencia@example.com',
    ]));

    expect(App\Support\IndividualQuotes\IndividualQuoteFollowUp::resolveRecipientEmails(collect([$agentQuote])))
        ->toBe(['agente@example.com'])
        ->and(App\Support\IndividualQuotes\IndividualQuoteFollowUp::resolveRecipientEmails(collect([$agencyQuote])))
        ->toBe(['agencia@example.com']);
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

it('envia el seguimiento al telefono y correo del agente o de la agencia y al cliente', function (): void {
    $jobSource = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendIndividualQuoteDayThreeFollowUp.php');
    $supportSource = file_get_contents(dirname(__DIR__, 2).'/app/Support/IndividualQuotes/IndividualQuoteFollowUp.php');

    expect($jobSource)
        ->toContain('IndividualQuoteFollowUp::resolveRecipientPhones($quotes)')
        ->toContain('IndividualQuoteFollowUp::resolveRecipientEmails($quotes)')
        ->toContain('IndividualQuoteFollowUpInternalCopies::dispatch')
        ->toContain('IndividualQuoteDayThreeFollowUp::whatsappBody')
        ->toContain('IndividualQuoteDayThreeFollowUp::clientWhatsappBody')
        ->toContain('dispatchClientFollowUpMessages')
        ->toContain('dispatchAllyEmailMessages')
        ->toContain('dispatchClientEmail')
        ->toContain('IndividualQuoteFollowUpMail')
        ->toContain('individual-quotes.day-three-follow-up.client');

    expect($supportSource)
        ->toContain("->where('status', self::ELIGIBLE_STATUS)")
        ->toContain('resolveAgentPhone')
        ->toContain('resolveAgencyPhone')
        ->toContain('resolveAgentEmail')
        ->toContain('resolveAgencyEmail')
        ->toContain('resolveRecipientEmails');
});
