<?php

declare(strict_types=1);

use App\Models\IndividualQuote;
use App\Support\Metrics\Corporate\CorporateQuoteEffectiveness;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Datos aislados en 2031, dentro de una transacción revertida.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    config(['cache.default' => 'array']);
});

afterEach(function (): void {
    DB::rollBack();
});

function effQuote(string $code, ?int $agentId, string $agency, string $status = 'PRE-APROBADA', string $createdAt = '2031-03-10 09:00:00'): int
{
    return DB::table('individual_quotes')->insertGetId([
        'code' => $code, 'plan' => '1', 'status' => $status, 'agent_id' => $agentId, 'code_agency' => $agency,
        'created_by' => 'test', 'created_at' => $createdAt, 'updated_at' => $createdAt,
    ]);
}

function effAffiliate(int $quoteId, ?int $agentId = null, bool $deleted = false): void
{
    DB::table('affiliations')->insert([
        'code' => 'EFF-AFF-'.uniqid(), 'code_agency' => 'TDG-999', 'status' => 'ACTIVA', 'plan_id' => 1,
        'individual_quote_id' => $quoteId, 'agent_id' => $agentId,
        'deleted_at' => $deleted ? '2031-03-20 10:00:00' : null,
        'created_at' => '2031-03-20 10:00:00', 'updated_at' => '2031-03-20 10:00:00',
    ]);
}

function effPeriod(): OperationsDashboardPeriod
{
    return OperationsDashboardPeriod::fromFilters(['period' => OperationsDashboardPeriod::LAST_MONTH], CarbonImmutable::parse('2031-04-15'));
}

/**
 * Agente A (id 990001): 10 de 30. Agente B (990002): 1 de 1. Agente C (990003): 0 de 6 (5 anuladas).
 */
function seedSalesForce(): void
{
    foreach (range(1, 30) as $i) {
        $id = effQuote('EFF-A-'.$i, 990001, 'TDG-950');

        if ($i <= 10) {
            effAffiliate($id, 990099);
        }
    }

    effAffiliate(effQuote('EFF-B-1', 990002, 'TDG-951'));

    foreach (range(1, 6) as $i) {
        effQuote('EFF-C-'.$i, 990003, 'TDG-100', $i <= 5 ? 'ANULADA' : 'PRE-APROBADA');
    }

    effQuote('EFF-SIN-AGENTE', null, 'TDG-100');
}

it('atribuye al agente que cotizó aunque la afiliación quede con otro agente', function (): void {
    seedSalesForce();

    $rows = collect(CorporateQuoteEffectiveness::build(effPeriod(), CorporateQuoteEffectiveness::AGENT)['rows'])->keyBy('key');

    expect($rows['990001'])->toMatchArray(['created' => 30, 'issued' => 10, 'rate' => 33.3, 'open' => 20])
        ->and($rows->has('990099'))->toBeFalse()
        ->and($rows['990003'])->toMatchArray(['created' => 6, 'issued' => 0, 'anulated' => 5, 'open' => 1]);
});

it('mejor efectividad ajusta por volumen y deja fuera a quien no llega al mínimo de cotizaciones', function (): void {
    seedSalesForce();

    $data = CorporateQuoteEffectiveness::build(effPeriod(), CorporateQuoteEffectiveness::AGENT);
    $rankings = CorporateQuoteEffectiveness::rankings($data['rows']);
    $effectiveness = array_column($rankings['effectiveness'], 'key');

    expect($effectiveness[0])->toBe('990001')
        ->and($effectiveness)->not->toContain('990002')
        ->and(array_column($rankings['conversions'], 'key'))->toBe(['990001', '990002'])
        ->and($rankings['volume'][0]['key'])->toBe('990001')
        ->and(CorporateQuoteEffectiveness::wilsonLowerBound(10, 30))->toBe(19.23)
        ->and(CorporateQuoteEffectiveness::wilsonLowerBound(2, 5))->toBe(11.76)
        ->and(CorporateQuoteEffectiveness::wilsonLowerBound(0, 6))->toBe(0.0);
});

it('las cotizaciones sin agente cuentan para la agencia y se informan en agentes', function (): void {
    seedSalesForce();

    $agents = CorporateQuoteEffectiveness::build(effPeriod(), CorporateQuoteEffectiveness::AGENT);
    $agencies = collect(CorporateQuoteEffectiveness::build(effPeriod(), CorporateQuoteEffectiveness::AGENCY)['rows'])->keyBy('key');

    expect($agents['without_agent'])->toBe(1)
        ->and($agencies['TDG-100'])->toMatchArray(['created' => 7, 'label' => 'Casa matriz (In House)'])
        ->and($agencies['TDG-950']['issued'])->toBe(10);
});

it('una afiliación eliminada no cuenta como conversión y una borrada sí cuenta como cotización', function (): void {
    $kept = effQuote('EFF-D-1', 990004, 'TDG-952');
    effAffiliate($kept, deleted: true);
    $gone = effQuote('EFF-D-2', 990004, 'TDG-952');
    IndividualQuote::query()->findOrFail($gone)->delete();

    $row = collect(CorporateQuoteEffectiveness::build(effPeriod(), CorporateQuoteEffectiveness::AGENT)['rows'])->firstWhere('key', '990004');

    expect($row)->toMatchArray(['created' => 2, 'issued' => 0, 'deleted' => 1, 'open' => 1]);
});

it('suma las corporativas del agente y compara con el período anterior', function (): void {
    effQuote('EFF-E-PREV', 990005, 'TDG-953', 'PRE-APROBADA', '2031-02-10 09:00:00');
    effAffiliate(effQuote('EFF-E-1', 990005, 'TDG-953'));
    DB::table('corporate_quotes')->insert([
        'code' => 'EFF-E-CORP', 'plan' => '1', 'status' => 'PRE-APROBADA', 'agent_id' => 990005, 'code_agency' => 'TDG-953',
        'created_by' => 'test', 'created_at' => '2031-03-11 09:00:00', 'updated_at' => '2031-03-11 09:00:00',
    ]);

    $row = collect(CorporateQuoteEffectiveness::build(effPeriod(), CorporateQuoteEffectiveness::AGENT)['rows'])->firstWhere('key', '990005');

    expect($row)->toMatchArray([
        'created' => 2, 'issued' => 1, 'individual_created' => 1, 'corporate_created' => 1,
        'previous_created' => 1, 'previous_issued' => 0, 'previous_rate' => 0.0, 'rate' => 50.0, 'rate_delta' => 50.0,
    ]);
});

it('la página Comercial muestra los dos rankings de efectividad', function (): void {
    $source = file_get_contents(app_path('Filament/Metrics/Pages/Comercial.php'));

    expect($source)->toContain('AgentQuoteEffectivenessRanking::class')->toContain('AgencyQuoteEffectivenessRanking::class');
});
