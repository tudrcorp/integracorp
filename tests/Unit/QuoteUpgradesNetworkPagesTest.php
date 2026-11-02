<?php

declare(strict_types=1);

use App\Enums\UpgradeNetworkVisibility;
use App\Filament\Agents\Resources\IndividualQuotes\Pages\CreateIndividualQuote as AgentCreateIndividualQuote;
use App\Filament\Shared\Quotes\QuoteUpgradesSection;
use App\Models\AgeRange;
use App\Models\CorporateQuote;
use App\Models\CorporateQuoteUpgrade;
use App\Models\PlanBenefitClinicalSetting;
use App\Models\Upgrade;
use App\Models\User;
use App\Support\ClinicalEntitlements\PlanClinicalCompleteness;
use App\Support\CorporateQuotePdfGenerator;
use App\Support\Quotes\IndividualQuoteUpgrades;
use App\Support\Upgrades\UpgradeCatalogPersistence;
use App\Support\Upgrades\UpgradePlanProjection;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Corre contra la base configurada: todo lo que escribe se revierte. Nunca se llama a `create`
 * de la página, que manda WhatsApp y genera el PDF.
 */
beforeEach(function (): void {
    DB::beginTransaction();

    $agency = DB::table('agents')->whereNotNull('owner_code')->where('status', 'ACTIVO')->whereNotNull('ownerAccountManagers')->first(['id', 'owner_code']);

    if ($agency === null) {
        $this->markTestSkipped('No hay un agente activo en esta conexión.');
    }

    $this->agentId = (int) $agency->id;

    Filament::setCurrentPanel('agents');
    Auth::setUser(User::factory()->make(['id' => 999_771, 'name' => 'Agente Pest', 'email' => 'agente.pest@test.invalid', 'status' => 'ACTIVO', 'is_agent' => true, 'agent_id' => $this->agentId]));
    $this->actingAs(Auth::user());
});

afterEach(fn () => DB::rollBack());

function pageUpgrade(string $name, UpgradeNetworkVisibility $visibility, array $agents = []): Upgrade
{
    return UpgradeCatalogPersistence::save(null, [
        'name' => $name,
        'status' => 'ACTIVO',
        'network_visibility' => $visibility->value,
        'network_agent_ids' => $agents,
        'coverages' => [['amount' => 2000, 'ranges' => [['age_from' => 0, 'age_to' => 120, 'price' => 50]]]],
    ]);
}

it('el agente solo puede tocar las tarjetas de los upgrades que tiene', function (): void {
    $mine = pageUpgrade('PEST MIO', UpgradeNetworkVisibility::Selected, [$this->agentId]);
    $private = pageUpgrade('PEST PRIVADO', UpgradeNetworkVisibility::BusinessOnly);

    $page = Livewire::test(AgentCreateIndividualQuote::class)
        ->assertSuccessful()
        ->call('toggleQuoteUpgrade', $private->getKey());

    expect($page->get('data.upgrades') ?? [])->toBe([]);

    $page->call('toggleQuoteUpgrade', $mine->getKey());
    $items = array_values((array) $page->get('data.upgrades'));

    expect($items)->toHaveCount(1)
        ->and((int) $items[0]['upgrade_id'])->toBe((int) $mine->getKey());

    $page->call('toggleQuoteUpgrade', $mine->getKey());

    expect((array) $page->get('data.upgrades'))->toBe([]);
});

it('sin upgrades disponibles el asistente del agente no gana un paso', function (): void {
    Upgrade::query()->update(['network_visibility' => UpgradeNetworkVisibility::BusinessOnly->value]);

    Livewire::test(AgentCreateIndividualQuote::class)->assertSuccessful()->assertDontSee('Upgrades opcionales:');
});

it('con un upgrade visible el asistente muestra el paso', function (): void {
    pageUpgrade('PEST TODOS', UpgradeNetworkVisibility::AllNetwork);

    Livewire::test(AgentCreateIndividualQuote::class)->assertSuccessful()->assertSee('Upgrades opcionales:');
});

it('lee la población de los repetidores de la red', function (): void {
    $range = AgeRange::query()->where('plan_id', 1)->first();

    if ($range === null) {
        $this->markTestSkipped('El plan 1 no tiene rangos en esta conexión.');
    }

    $form = [
        'plan' => '1',
        'details_quote_plan_inicial' => [['plan_id' => 1, 'age_range_id' => $range->id, 'total_persons' => 3]],
    ];

    expect(QuoteUpgradesSection::rateRows(fn (string $field): mixed => $form[$field] ?? null, network: true))
        ->toBe([['age_range_label' => trim((string) $range->range), 'population' => 3]])
        ->and(QuoteUpgradesSection::rateRows(fn (string $field): mixed => $form[$field] ?? null, network: false))->toBe([]);
});

it('Taylor Made nunca lleva upgrades', function (): void {
    expect(QuoteUpgradesSection::isTaylorMade('DRESS-TAILOR'))->toBeTrue()
        ->and(QuoteUpgradesSection::isTaylorMade('BASICO'))->toBeFalse()
        ->and(QuoteUpgradesSection::isTaylorMade(null))->toBeFalse();
});

it('guarda la copia congelada en la cotización corporativa', function (): void {
    $range = AgeRange::query()->where('plan_id', 1)->first();

    if ($range === null) {
        $this->markTestSkipped('El plan 1 no tiene rangos en esta conexión.');
    }

    $upgrade = pageUpgrade('PEST CORPORATIVO', UpgradeNetworkVisibility::AllNetwork);
    $quote = CorporateQuote::query()->forceCreate([
        'code' => 'COT-COR-PEST-'.uniqid(),
        'full_name' => 'EMPRESA PEST',
        'status' => 'PRE-APROBADA',
        'plan' => '1',
        'created_by' => 'Pest',
    ]);
    $rateRows = [['age_range_label' => '0 a 120', 'population' => 12]];
    $state = [['line_key' => 'pest-line', 'upgrade_id' => $upgrade->getKey(), 'upgrade_coverage_id' => $upgrade->coverages()->value('id'), 'excluded_ranges' => [], 'manual_ranges' => []]];

    IndividualQuoteUpgrades::persist($quote, $state, $rateRows);

    $line = CorporateQuoteUpgrade::query()->where('corporate_quote_id', $quote->id)->sole();
    $summary = IndividualQuoteUpgrades::summary($quote);

    expect((float) $line->annual_amount)->toBe(600.0)
        ->and($line->persons)->toBe(12)
        ->and($summary['annual_total'])->toBe(600.0)
        ->and($summary['lines'][0]['name'])->toBe('PEST CORPORATIVO')
        ->and(CorporateQuotePdfGenerator::hasUpgrades($quote))->toBeTrue();
});

it('abre los formularios de crear cotización de cada panel con el upgrade disponible', function (string $panel, string $page, string $expected): void {
    pageUpgrade('PEST TODOS', UpgradeNetworkVisibility::AllNetwork);

    $agency = DB::table('agencies')->where('status', 'ACTIVO')->whereNotNull('code')->where('code', '!=', 'TDG-100')->first(['code', 'owner_code']);

    if ($agency === null) {
        $this->markTestSkipped('No hay una agencia activa en esta conexión.');
    }

    $user = match ($panel) {
        'business' => User::factory()->make(['id' => 999_773, 'name' => 'Analista Pest', 'email' => 'analista.pest@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['SUPERADMIN', 'NEGOCIOS']]),
        'agents' => Auth::user(),
        default => User::factory()->make(['id' => 999_774, 'name' => 'Agencia Pest', 'email' => 'agencia.pest@test.invalid', 'status' => 'ACTIVO', 'is_agency' => true, 'agency_type' => mb_strtoupper($panel), 'code_agency' => $agency->code]),
    };

    Filament::setCurrentPanel($panel);
    Auth::setUser($user);
    $this->actingAs($user);

    Livewire::test($page)->assertSuccessful()->assertSee($expected);
})->with([
    'agente corporativa' => ['agents', \App\Filament\Agents\Resources\CorporateQuotes\Pages\CreateCorporateQuote::class, 'Upgrades opcionales:'],
    'master individual' => ['master', \App\Filament\Master\Resources\IndividualQuotes\Pages\CreateIndividualQuote::class, 'Upgrades opcionales:'],
    'master corporativa' => ['master', \App\Filament\Master\Resources\CorporateQuotes\Pages\CreateCorporateQuote::class, 'Upgrades opcionales:'],
    'general individual' => ['general', \App\Filament\General\Resources\IndividualQuotes\Pages\CreateIndividualQuote::class, 'Upgrades opcionales:'],
    'general corporativa' => ['general', \App\Filament\General\Resources\CorporateQuotes\Pages\CreateCorporateQuote::class, 'Upgrades opcionales:'],
    'negocios individual' => ['business', \App\Filament\Business\Resources\IndividualQuotes\Pages\CreateIndividualQuote::class, 'PLANES A COTIZAR'],
    'negocios corporativa' => ['business', \App\Filament\Business\Resources\CorporateQuotes\Pages\CreateCorporateQuote::class, 'PLANES A COTIZAR'],
]);

it('el agente cotiza un plan nacido de un upgrade con su propio repetidor de rangos', function (): void {
    $upgrade = UpgradeCatalogPersistence::save(null, [
        'name' => 'PEST PLAN RED',
        'status' => 'ACTIVO',
        'network_visibility' => UpgradeNetworkVisibility::AllNetwork->value,
        'is_plan' => true,
        'plan_business_unit_id' => 1,
        'services' => [['name' => 'pest servicio red']],
        'coverages' => [['amount' => 2000, 'ranges' => [['age_from' => 0, 'age_to' => 70, 'price' => 30]]]],
    ]);
    $plan = UpgradePlanProjection::planOf($upgrade);

    foreach (PlanClinicalCompleteness::assignedBenefitIds($plan) as $benefitId) {
        PlanBenefitClinicalSetting::query()->create(['plan_id' => $plan->id, 'benefit_id' => $benefitId, 'applies_clinically' => false]);
    }

    expect($plan->refresh()->status)->toBe('ACTIVO');

    Livewire::test(AgentCreateIndividualQuote::class)
        ->assertSee('PEST PLAN RED')
        ->set('data.plan', (string) $plan->id)
        ->assertSuccessful()
        ->assertSee('Añadir rango de edad')
        ->assertSee('0 a 70');

    Livewire::test(\App\Filament\Agents\Resources\CorporateQuotes\Pages\CreateCorporateQuote::class)
        ->assertSee('PEST PLAN RED')
        ->set('data.plan', (string) $plan->id)
        ->assertSuccessful()
        ->assertSee('Añadir rango de edad');
});

it('el agente no ve el plan si su upgrade es Solo Negocios', function (): void {
    $upgrade = UpgradeCatalogPersistence::save(null, [
        'name' => 'PEST PLAN PRIVADO',
        'status' => 'ACTIVO',
        'is_plan' => true,
        'plan_business_unit_id' => 1,
        'services' => [['name' => 'pest servicio privado']],
        'coverages' => [['amount' => 2000, 'ranges' => [['age_from' => 0, 'age_to' => 70, 'price' => 30]]]],
    ]);
    $plan = UpgradePlanProjection::planOf($upgrade);

    foreach (PlanClinicalCompleteness::assignedBenefitIds($plan) as $benefitId) {
        PlanBenefitClinicalSetting::query()->create(['plan_id' => $plan->id, 'benefit_id' => $benefitId, 'applies_clinically' => false]);
    }

    Livewire::test(AgentCreateIndividualQuote::class)->assertDontSee('PEST PLAN PRIVADO');
});
