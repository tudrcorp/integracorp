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

it('arma el mensaje de whatsapp directo al cliente cotizado en el dia 12', function (): void {
    $quote = new IndividualQuote([
        'code' => 'COT-IND-000264',
        'full_name' => 'María García',
        'phone' => '04121234567',
    ]);

    $body = IndividualQuoteDayTwelveFollowUp::clientWhatsappBody($quote);

    expect($body)
        ->toContain('¡Hola, *María García*! 😊')
        ->toContain('Tu Doctor en Casa 🩺🏡')
        ->toContain('Tu cotización vence pronto')
        ->toContain('tu tranquilidad es lo primero')
        ->toContain('ajustar el presupuesto o ver un plan a tu medida')
        ->toContain('lo agendamos hoy mismo')
        ->not->toContain('*El sistema automatizado*');
});

it('usa Cliente como nombre por defecto si la cotizacion no tiene full_name en el dia 12', function (): void {
    $quote = new IndividualQuote([
        'code' => 'COT-IND-000264',
        'full_name' => null,
    ]);

    expect(IndividualQuoteDayTwelveFollowUp::clientWhatsappBody($quote))
        ->toContain('¡Hola, *Cliente*! 😊');
});

it('programa el seguimiento de cotizaciones individuales a las 8:40am', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/routes/console.php');

    expect($source)
        ->toContain('SendIndividualQuoteDayTwelveFollowUp')
        ->toContain("->dailyAt('8:40')")
        ->toContain('->when($individualQuoteFollowUpIsActive)');
});

it('envia el seguimiento de 12 dias por whatsapp y correo al aliado y al cliente', function (): void {
    $jobSource = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendIndividualQuoteDayTwelveFollowUp.php');

    expect($jobSource)
        ->toContain('IndividualQuoteFollowUp::resolveRecipientPhones($quotes)')
        ->toContain('IndividualQuoteFollowUp::resolveRecipientEmails($quotes)')
        ->toContain('IndividualQuoteFollowUpInternalCopies::dispatch')
        ->toContain('IndividualQuoteDayTwelveFollowUp::whatsappBody')
        ->toContain('IndividualQuoteDayTwelveFollowUp::clientWhatsappBody')
        ->toContain('dispatchClientFollowUpMessages')
        ->toContain('dispatchAllyEmailMessages')
        ->toContain('dispatchClientEmail')
        ->toContain('IndividualQuoteFollowUpMail')
        ->toContain('individual-quotes.day-twelve-follow-up.client');
});
