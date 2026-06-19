<?php

declare(strict_types=1);

use App\Support\IndividualQuotes\IndividualQuoteFollowUp;
use Illuminate\Support\Carbon;

uses(Tests\TestCase::class);

it('envia seguimiento de cotizaciones a los telefonos internos configurados', function (): void {
    expect(IndividualQuoteFollowUp::reportPhones())
        ->toBe(['04127018390', '04143027250']);
});

it('activa el seguimiento programado a partir de la fecha configurada', function (): void {
    config(['individual-quotes.follow_up_scheduling_start_date' => '2026-06-18']);

    Carbon::setTestNow(Carbon::parse('2026-06-17 10:00:00', config('app.timezone')));
    expect(IndividualQuoteFollowUp::isSchedulingActive())->toBeFalse();

    Carbon::setTestNow(Carbon::parse('2026-06-18 08:00:00', config('app.timezone')));
    expect(IndividualQuoteFollowUp::isSchedulingActive())->toBeTrue();

    Carbon::setTestNow();
});

it('programa las tareas de seguimiento con condicion de inicio y telefonos centralizados', function (): void {
    $console = file_get_contents(dirname(__DIR__, 2).'/routes/console.php');
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendIndividualQuoteDayThreeFollowUp.php');

    expect($console)
        ->toContain('$individualQuoteFollowUpIsActive')
        ->toContain('->when($individualQuoteFollowUpIsActive)')
        ->toContain("Schedule::job(new SendIndividualQuoteDayTwelveFollowUp, 'system')");

    expect($job)->toContain('IndividualQuoteFollowUp::reportPhones()');
});
