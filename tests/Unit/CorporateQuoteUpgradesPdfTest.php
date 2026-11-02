<?php

declare(strict_types=1);

use App\Models\CorporateQuote;
use App\Models\DetailCorporateQuote;
use App\Models\Fee;
use App\Models\Upgrade;
use App\Models\User;
use App\Support\CorporateQuotePdfGenerator;
use App\Support\IndividualQuotePdfLayout;
use App\Support\Quotes\IndividualQuoteProposalPages;
use App\Support\Quotes\IndividualQuoteUpgrades;
use App\Support\TuDrQuote\QuoteDocumentLayout;
use App\Support\Upgrades\UpgradeCatalogPersistence;
use App\Support\Upgrades\UpgradePlanProjection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Lo escrito se revierte; el PDF no se guarda en disco del portal, solo se dibuja.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Auth::setUser(User::factory()->make(['id' => 999_772, 'name' => 'Agente Pest']));
});

afterEach(fn () => DB::rollBack());

/**
 * @return array{0: CorporateQuote, 1: int}
 */
function corporateQuoteWithDetail(int $planId): array
{
    $fee = Fee::query()->whereHas('ageRange', fn ($query) => $query->where('plan_id', $planId))->whereNotNull('coverage_id')->with('ageRange')->first()
        ?? Fee::query()->whereHas('ageRange', fn ($query) => $query->where('plan_id', $planId))->with('ageRange')->first();

    if ($fee === null) {
        test()->markTestSkipped('El plan no tiene tarifas en esta conexión.');
    }

    $quote = CorporateQuote::query()->forceCreate([
        'code' => 'COT-COR-PEST-'.uniqid(),
        'full_name' => 'EMPRESA PEST',
        'status' => 'PRE-APROBADA',
        'plan' => (string) $planId,
        'created_by' => 'Pest',
    ]);

    DetailCorporateQuote::query()->forceCreate([
        'corporate_quote_id' => $quote->id,
        'plan_id' => $planId,
        'age_range_id' => $fee->age_range_id,
        'coverage_id' => $fee->coverage_id,
        'fee' => $fee->price,
        'total_persons' => 12,
        'subtotal_anual' => 12 * $fee->price,
        'subtotal_quarterly' => 12 * $fee->price / 4,
        'subtotal_biannual' => 12 * $fee->price / 2,
        'subtotal_monthly' => 12 * $fee->price / 12,
        'status' => 'PRE-APROBADA',
        'created_by' => 'Pest',
    ]);

    return [$quote, (int) $fee->age_range_id];
}

function attachCorporateUpgrade(CorporateQuote $quote): void
{
    $upgrade = UpgradeCatalogPersistence::save(null, [
        'name' => 'PEST FUNERARIO',
        'status' => Upgrade::STATUS_ACTIVE,
        'coverages' => [['amount' => 2000, 'ranges' => [['age_from' => 0, 'age_to' => 120, 'price' => 50]]]],
    ]);

    IndividualQuoteUpgrades::persist($quote, [[
        'line_key' => 'pest-line',
        'upgrade_id' => $upgrade->getKey(),
        'upgrade_coverage_id' => $upgrade->coverages()->value('id'),
        'excluded_ranges' => [],
        'manual_ranges' => [],
    ]], [['age_range_label' => '0 a 120', 'population' => 12]]);
}

it('sin upgrades la propuesta corporativa no cambia de plantilla', function (): void {
    [$quote] = corporateQuoteWithDetail(2);

    expect(CorporateQuotePdfGenerator::hasUpgrades($quote))->toBeFalse()
        ->and(IndividualQuoteProposalPages::appliesTo(QuoteDocumentLayout::SCOPE_CORPORATE, $quote->id))->toBeFalse()
        ->and(IndividualQuoteProposalPages::appliesTo(QuoteDocumentLayout::SCOPE_INDIVIDUAL, $quote->id))->toBeTrue()
        ->and(CorporateQuotePdfGenerator::detailsPayload($quote, 2, IndividualQuotePdfLayout::resolve(2))['upgrades']['lines'])->toBe([]);
});

it('con upgrades dibuja la página del plan con ellos debajo', function (): void {
    [$quote] = corporateQuoteWithDetail(2);
    attachCorporateUpgrade($quote);

    $layout = IndividualQuotePdfLayout::resolveForIndividual(2);
    $details = CorporateQuotePdfGenerator::detailsPayload($quote, 2, $layout);

    expect(IndividualQuoteProposalPages::appliesTo(QuoteDocumentLayout::SCOPE_CORPORATE, $quote->id))->toBeTrue()
        ->and($details['upgrades']['annual_total'])->toBe(600.0);

    $pdf = IndividualQuoteProposalPages::renderPlanPage($details);

    expect(str_starts_with($pdf, '%PDF'))->toBeTrue();
});

it('dibuja la propuesta de un plan nacido de un upgrade', function (): void {
    $upgrade = UpgradeCatalogPersistence::save(null, [
        'name' => 'PEST PLAN FUNERARIO',
        'status' => Upgrade::STATUS_ACTIVE,
        'is_plan' => true,
        'plan_business_unit_id' => 1,
        'services' => [['name' => 'pest asistencia funeraria'], ['name' => 'pest traslado']],
        'coverages' => [
            ['amount' => 2000, 'ranges' => [['age_from' => 0, 'age_to' => 40, 'price' => 30], ['age_from' => 41, 'age_to' => 70, 'price' => 60]]],
            ['amount' => 5000, 'ranges' => [['age_from' => 0, 'age_to' => 40, 'price' => 50], ['age_from' => 41, 'age_to' => 70, 'price' => 90]]],
        ],
    ]);
    $plan = UpgradePlanProjection::planOf($upgrade);
    $quote = CorporateQuote::query()->forceCreate(['code' => 'COT-COR-PEST-'.uniqid(), 'full_name' => 'EMPRESA PEST', 'status' => 'PRE-APROBADA', 'plan' => (string) $plan->id, 'created_by' => 'Pest']);

    foreach (Fee::query()->where('plan_id', $plan->id)->get() as $fee) {
        DetailCorporateQuote::query()->forceCreate([
            'corporate_quote_id' => $quote->id,
            'plan_id' => $plan->id,
            'age_range_id' => $fee->age_range_id,
            'coverage_id' => $fee->coverage_id,
            'fee' => $fee->price,
            'total_persons' => 5,
            'subtotal_anual' => 5 * $fee->price,
            'subtotal_quarterly' => 5 * $fee->price / 4,
            'subtotal_biannual' => 5 * $fee->price / 2,
            'subtotal_monthly' => 5 * $fee->price / 12,
            'status' => 'PRE-APROBADA',
            'created_by' => 'Pest',
        ]);
    }

    $layout = IndividualQuotePdfLayout::resolve((int) $plan->id);

    expect($layout)->toBe(IndividualQuotePdfLayout::Estructura);

    $pdf = IndividualQuoteProposalPages::renderPlanPage(CorporateQuotePdfGenerator::detailsPayload($quote, (int) $plan->id, $layout));

    expect(str_starts_with($pdf, '%PDF'))->toBeTrue();
});
