<?php

declare(strict_types=1);

use App\Models\Agency;
use App\Models\Agent;
use App\Models\IndividualQuote;
use App\Support\IndividualQuotes\IndividualQuoteFollowUp;
use Illuminate\Support\Carbon;

uses(Tests\TestCase::class);

it('resuelve el telefono del agente cuando la cotizacion tiene agent_id', function (): void {
    $quote = new IndividualQuote([
        'agent_id' => 10,
        'code_agency' => 'AG-001',
    ]);
    $quote->setRelation('agent', new Agent([
        'id' => 10,
        'name' => 'Juan Pérez',
        'phone' => '04121234567',
    ]));

    expect(IndividualQuoteFollowUp::resolveRecipientPhones(collect([$quote])))
        ->toBe(['04121234567']);
});

it('resuelve el telefono de la agencia cuando agent_id esta vacio', function (): void {
    $quote = new IndividualQuote([
        'agent_id' => null,
        'code_agency' => 'AG-002',
    ]);
    $quote->setRelation('agency', new Agency([
        'code' => 'AG-002',
        'name_corporative' => 'Agencia Demo',
        'phone' => '04149876543',
    ]));

    expect(IndividualQuoteFollowUp::resolveRecipientPhones(collect([$quote])))
        ->toBe(['04149876543']);
});

it('no resuelve telefono cuando el aliado no tiene numero configurado', function (): void {
    $quote = new IndividualQuote([
        'agent_id' => 10,
        'code_agency' => 'AG-001',
    ]);
    $quote->setRelation('agent', new Agent([
        'id' => 10,
        'name' => 'Sin Teléfono',
        'phone' => null,
    ]));

    expect(IndividualQuoteFollowUp::resolveRecipientPhones(collect([$quote])))
        ->toBe([]);
});

it('activa el seguimiento programado a partir de la fecha configurada', function (): void {
    config(['individual-quotes.follow_up_scheduling_start_date' => '2026-06-18']);

    Carbon::setTestNow(Carbon::parse('2026-06-17 10:00:00', config('app.timezone')));
    expect(IndividualQuoteFollowUp::isSchedulingActive())->toBeFalse();

    Carbon::setTestNow(Carbon::parse('2026-06-18 08:00:00', config('app.timezone')));
    expect(IndividualQuoteFollowUp::isSchedulingActive())->toBeTrue();

    Carbon::setTestNow();
});

it('programa las tareas de seguimiento con condicion de inicio y telefonos del aliado', function (): void {
    $console = file_get_contents(dirname(__DIR__, 2).'/routes/console.php');
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendIndividualQuoteDayThreeFollowUp.php');

    expect($console)
        ->toContain('$individualQuoteFollowUpIsActive')
        ->toContain('->when($individualQuoteFollowUpIsActive)')
        ->toContain('SystemNotificationRecipients::isActive(SystemNotificationKey::IndividualQuoteFollowUp)')
        ->toContain("Schedule::job(new SendIndividualQuoteDayTwelveFollowUp, 'system')")
        ->toContain('teléfono del agente (agent_id) o de la agencia (code_agency)');

    expect($job)->toContain('IndividualQuoteFollowUp::resolveRecipientPhones($quotes)');
});
