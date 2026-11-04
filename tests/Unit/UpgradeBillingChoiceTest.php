<?php

declare(strict_types=1);

use App\Filament\Shared\Affiliations\UpgradeBillingField;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Support\PlanGenerators\PlanGeneratorGroupTotalCalculator;
use App\Support\PlanGenerators\PlanGeneratorPreAffiliationSession;
use App\Support\Upgrades\UpgradeBillingChoice;
use App\Support\Upgrades\UpgradeFirstPaymentBilling;
use Illuminate\Validation\ValidationException;

/**
 * Solo lee: modelos en memoria y la sesión del request de prueba. No toca la base.
 */
uses(Tests\TestCase::class);

afterEach(fn () => session()->forget(PlanGeneratorPreAffiliationSession::SESSION_KEY));

it('solo admite Anual o la misma frecuencia de la afiliación', function (string $choice, string $frequency, ?string $error): void {
    $result = UpgradeBillingChoice::error($choice, $frequency);

    $error === null
        ? expect($result)->toBeNull()
        : expect($result)->toContain($error);
})->with([
    'anual en trimestral' => ['ANUAL', 'TRIMESTRAL', null],
    'misma frecuencia' => ['TRIMESTRAL', 'TRIMESTRAL', null],
    'mensual en mensual' => ['MENSUAL', 'MENSUAL', null],
    'anual en anual' => ['ANUAL', 'ANUAL', null],
    'semestral en trimestral' => ['SEMESTRAL', 'TRIMESTRAL', 'debe ser igual a la frecuencia de pago de la afiliación (Trimestral)'],
    'mensual en anual' => ['MENSUAL', 'ANUAL', 'Elija Anual o Anual.'],
    'inventada' => ['QUINCENAL', 'TRIMESTRAL', 'Indique cómo se cobrará el upgrade.'],
]);

it('decide el modo de cobro de cada asignación', function (?string $choice, ?string $frequency, string $mode): void {
    expect(UpgradeBillingChoice::modeFor($choice, $frequency))->toBe($mode);
})->with([
    'anual' => ['ANUAL', 'TRIMESTRAL', UpgradeFirstPaymentBilling::MODE],
    'repartido' => ['TRIMESTRAL', 'TRIMESTRAL', UpgradeFirstPaymentBilling::MODE_INSTALLMENTS],
    'afiliación anual' => ['ANUAL', 'ANUAL', UpgradeFirstPaymentBilling::MODE],
    'sin elección' => [null, 'MENSUAL', UpgradeFirstPaymentBilling::MODE],
    'elección que no cuadra' => ['SEMESTRAL', 'TRIMESTRAL', UpgradeFirstPaymentBilling::MODE],
]);

it('una afiliación sin elección o con una que ya no cuadra cobra Anual', function (): void {
    expect(UpgradeBillingChoice::of(new Affiliation(['payment_frequency' => 'TRIMESTRAL'])))->toBe('ANUAL')
        ->and(UpgradeBillingChoice::of(new Affiliation(['payment_frequency' => 'MENSUAL', 'upgrade_billing_frequency' => 'TRIMESTRAL'])))->toBe('ANUAL')
        ->and(UpgradeBillingChoice::of(new AffiliationCorporate(['payment_frequency' => 'TRIMESTRAL', 'upgrade_billing_frequency' => 'TRIMESTRAL'])))->toBe('TRIMESTRAL')
        ->and(UpgradeBillingChoice::modeOf(new AffiliationCorporate(['payment_frequency' => 'TRIMESTRAL', 'upgrade_billing_frequency' => 'TRIMESTRAL'])))->toBe(UpgradeFirstPaymentBilling::MODE_INSTALLMENTS);
});

it('al cambiar la frecuencia, el reparto sigue a la nueva y el Anual no cambia', function (): void {
    $spread = new Affiliation(['payment_frequency' => 'MENSUAL', 'upgrade_billing_frequency' => 'TRIMESTRAL']);
    UpgradeBillingChoice::followFrequency($spread, 'TRIMESTRAL');

    $annual = new Affiliation(['payment_frequency' => 'MENSUAL', 'upgrade_billing_frequency' => 'ANUAL']);
    UpgradeBillingChoice::followFrequency($annual, 'TRIMESTRAL');

    $none = new Affiliation(['payment_frequency' => 'MENSUAL']);
    UpgradeBillingChoice::followFrequency($none, 'TRIMESTRAL');

    expect($spread->upgrade_billing_frequency)->toBe('MENSUAL')
        ->and($annual->upgrade_billing_frequency)->toBe('ANUAL')
        ->and($none->upgrade_billing_frequency)->toBeNull();
});

it('el total grupal reparte los upgrades solo en la frecuencia elegida', function (): void {
    $columns = [['column_key' => 'c1', 'header_label' => 'Plan']];
    $rateRows = ['r1' => ['label' => '0-60', 'population' => 1, 'cells' => ['c1' => ['rate_amount' => 400]]]];

    $annual = PlanGeneratorGroupTotalCalculator::totalsByColumn($columns, $rateRows, 120.0);
    $spread = PlanGeneratorGroupTotalCalculator::totalsByColumn($columns, $rateRows, 120.0, 'TRIMESTRAL');
    $planAnnual = $annual['annual']['c1'] - 120.0;

    expect($planAnnual)->toBe(400.0)
        ->and($spread['annual']['c1'])->toBe($annual['annual']['c1'])
        ->and($spread['trimestral']['c1'])->toBe(($planAnnual + 120.0) / 4)
        ->and($spread['trimestral_next']['c1'])->toBe(($planAnnual + 120.0) / 4)
        ->and($spread['semestral']['c1'])->toBe($annual['semestral']['c1'])
        ->and($spread['semestral_next']['c1'])->toBe($planAnnual / 2)
        ->and($annual['trimestral']['c1'])->toBe($planAnnual / 4 + 120.0);
});

it('la tabla del total grupal y la regla de la cotización dicen cómo se paga', function (): void {
    $rows = PlanGeneratorGroupTotalCalculator::displayRows(false, [], 120.0, 'TRIMESTRAL');
    $labels = array_column($rows, 'label');

    expect($labels)->toContain('Tarifa Trimestral (upgrades en cada cuota)')
        ->and($labels)->toContain('Tarifa Semestral · 1.er pago')
        ->and(collect($rows)->where('frequency_key', 'trimestral')->count())->toBe(1)
        ->and(PlanGeneratorGroupTotalCalculator::displayRows(false, [], 120.0))->toBe(PlanGeneratorGroupTotalCalculator::displayRows(false, [], 120.0, 'ANUAL'))
        ->and(PlanGeneratorGroupTotalCalculator::upgradeRuleText('TRIMESTRAL'))->toContain('Con pago trimestral los upgrades van repartidos en cada cuota')
        ->and(PlanGeneratorGroupTotalCalculator::upgradeRuleText(null))->toContain('una sola vez al año');
});

it('al afiliar desde una cotización con upgrades sugiere lo elegido en ella y bloquea una frecuencia distinta', function (): void {
    session()->put(PlanGeneratorPreAffiliationSession::SESSION_KEY, ['type' => 'individual', 'has_upgrades' => true, 'upgrade_billing_frequencies' => ['ANUAL', 'TRIMESTRAL']]);

    expect(PlanGeneratorPreAffiliationSession::hasUpgrades())->toBeTrue()
        ->and(PlanGeneratorPreAffiliationSession::offeredUpgradeBillingFrequencies())->toBe(['ANUAL', 'TRIMESTRAL'])
        ->and(PlanGeneratorPreAffiliationSession::suggestedUpgradeBillingFrequency('TRIMESTRAL'))->toBe('TRIMESTRAL')
        ->and(PlanGeneratorPreAffiliationSession::suggestedUpgradeBillingFrequency('MENSUAL'))->toBe('ANUAL')
        ->and(PlanGeneratorPreAffiliationSession::suggestedUpgradeBillingFrequency(null))->toBe('ANUAL')
        ->and(UpgradeBillingField::normalizeForCreate(['payment_frequency' => 'TRIMESTRAL', 'upgrade_billing_frequency' => 'TRIMESTRAL'])['upgrade_billing_frequency'])->toBe('TRIMESTRAL')
        ->and(UpgradeBillingField::normalizeForCreate(['payment_frequency' => 'MENSUAL', 'upgrade_billing_frequency' => 'ANUAL'])['upgrade_billing_frequency'])->toBe('ANUAL')
        ->and(fn () => UpgradeBillingField::normalizeForCreate(['payment_frequency' => 'MENSUAL', 'upgrade_billing_frequency' => 'TRIMESTRAL']))
        ->toThrow(ValidationException::class, 'debe ser igual a la frecuencia de pago de la afiliación (Mensual)');
});

it('sin upgrades en la cotización no se pregunta ni se guarda la elección', function (): void {
    session()->put(PlanGeneratorPreAffiliationSession::SESSION_KEY, ['type' => 'individual', 'has_upgrades' => false]);

    expect(PlanGeneratorPreAffiliationSession::hasUpgrades())->toBeFalse()
        ->and(PlanGeneratorPreAffiliationSession::suggestedUpgradeBillingFrequency())->toBe('ANUAL')
        ->and(UpgradeBillingField::normalizeForCreate(['payment_frequency' => 'MENSUAL', 'upgrade_billing_frequency' => 'TRIMESTRAL'])['upgrade_billing_frequency'])->toBeNull();

    session()->forget(PlanGeneratorPreAffiliationSession::SESSION_KEY);

    expect(UpgradeBillingField::normalizeForCreate(['payment_frequency' => 'MENSUAL'])['upgrade_billing_frequency'])->toBeNull();
});

it('el campo y la regla están cableados en las pantallas de Negocios y en el generador', function (): void {
    $root = dirname(__DIR__, 2).'/';
    $read = fn (string $path): string => (string) file_get_contents($root.$path);

    expect($read('app/Filament/Business/Resources/Affiliations/Schemas/AffiliationForm.php'))->toContain('UpgradeBillingField::make()')
        ->and($read('app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateForm.php'))->toContain('UpgradeBillingField::make()')
        ->and($read('app/Filament/Business/Resources/Affiliations/Pages/CreateAffiliation.php'))->toContain('UpgradeBillingField::normalizeForCreate($data)')
        ->and($read('app/Filament/Business/Resources/AffiliationCorporates/Pages/CreateAffiliationCorporate.php'))->toContain('UpgradeBillingField::normalizeForCreate($data)')
        ->and($read('app/Filament/Business/Resources/PlanGenerators/Schemas/PlanGeneratorForm.php'))->toContain("ToggleButtons::make('upgrade_billing_frequencies')")
        ->and($read('app/Filament/Business/Resources/PlanGenerators/Schemas/PlanGeneratorForm.php'))->toContain('->multiple()')
        ->and($read('app/Filament/Business/Resources/Affiliations/Schemas/AffiliationForm.php'))->toContain('UpgradeBillingField::syncWithFrequency($set, $state)')
        ->and($read('app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateForm.php'))->toContain("UpgradeBillingField::syncWithFrequency(\$set, \$get('payment_frequency'))")
        ->and($read('app/Support/PlanGenerators/PlanGeneratorUpgradeAssignment.php'))->toContain("assignmentAttributes('affiliate_corporate_upgrades', \$mode)")
        ->and($read('app/Filament/Business/Concerns/AssignsUpgradesWithIlsVoucher.php'))->toContain('$this->upgradeBillingChoiceField()')
        ->and($read('app/Support/PlanGenerators/PlanGeneratorTemplateCloner.php'))->toContain("\$attributes['upgrade_billing_frequencies'] = \$template->upgradeBillingFrequencies();")
        ->and($read('resources/views/documents/partials/plan-generator-calc-group.blade.php'))->toContain('upgradeRuleText');
});

it('la fila de la frecuencia elegida se muestra aunque el analista la haya ocultado', function (): void {
    [$monthly, $hidden] = PlanGeneratorGroupTotalCalculator::visibilityForUpgradeBilling(false, ['semestral', 'trimestral'], 'TRIMESTRAL', 120.0);
    [$monthlyForced] = PlanGeneratorGroupTotalCalculator::visibilityForUpgradeBilling(false, [], 'MENSUAL', 120.0);
    [, $untouched] = PlanGeneratorGroupTotalCalculator::visibilityForUpgradeBilling(false, ['trimestral'], 'TRIMESTRAL', 0.0);

    expect($monthly)->toBeFalse()
        ->and($hidden)->toBe(['semestral'])
        ->and($monthlyForced)->toBeTrue()
        ->and($untouched)->toBe(['trimestral'])
        ->and(array_column(PlanGeneratorGroupTotalCalculator::displayRows(false, ['semestral', 'trimestral'], 120.0, 'TRIMESTRAL'), 'key'))->toBe(['annual', 'trimestral']);
});

it('el generador admite varias formas de pago y cada una sale repartida en su fila', function (): void {
    $columns = [['column_key' => 'c1', 'header_label' => 'Plan']];
    $rateRows = ['r1' => ['label' => '0-60', 'population' => 1, 'cells' => ['c1' => ['rate_amount' => 400]]]];
    $totals = PlanGeneratorGroupTotalCalculator::totalsByColumn($columns, $rateRows, 120.0, ['ANUAL', 'TRIMESTRAL', 'MENSUAL']);
    $rows = PlanGeneratorGroupTotalCalculator::displayRows(false, ['trimestral'], 120.0, ['MENSUAL', 'ANUAL', 'TRIMESTRAL']);

    expect($totals['annual']['c1'])->toBe(520.0)
        ->and($totals['trimestral']['c1'])->toBe(130.0)
        ->and($totals['mensual']['c1'])->toBe(520.0 / 12)
        ->and($totals['semestral']['c1'])->toBe(320.0)
        ->and($totals['semestral_next']['c1'])->toBe(200.0)
        ->and(array_column($rows, 'label'))->toBe([
            'Tarifa anual (pago único)',
            'Tarifa Semestral · 1.er pago',
            'Tarifa Semestral · 2.º pago',
            'Tarifa Trimestral (upgrades en cada cuota)',
            'Total Mensual (upgrades en cada cuota)',
        ])
        ->and(PlanGeneratorGroupTotalCalculator::upgradeRuleText(['TRIMESTRAL', 'MENSUAL']))->toContain('Con pago trimestral o mensual los upgrades van repartidos')
        ->and(PlanGeneratorGroupTotalCalculator::upgradeRuleText(['SEMESTRAL', 'TRIMESTRAL', 'MENSUAL']))->toContain('semestral, trimestral o mensual');
});

it('normaliza la lista de formas de pago y la cotización vieja de una sola elección sigue sirviendo', function (): void {
    expect(UpgradeBillingChoice::normalizeList(['mensual', 'ANUAL', 'MENSUAL', 'QUINCENAL', null]))->toBe(['ANUAL', 'MENSUAL'])
        ->and(UpgradeBillingChoice::normalizeList('["TRIMESTRAL"]'))->toBe(['TRIMESTRAL'])
        ->and(UpgradeBillingChoice::suggestFor(['ANUAL', 'SEMESTRAL'], 'SEMESTRAL'))->toBe('SEMESTRAL')
        ->and(UpgradeBillingChoice::suggestFor(['ANUAL', 'SEMESTRAL'], 'TRIMESTRAL'))->toBe('ANUAL')
        ->and((new App\Models\PlanGenerator(['upgrade_billing_frequencies' => ['TRIMESTRAL', 'ANUAL']]))->upgradeBillingFrequencies())->toBe(['ANUAL', 'TRIMESTRAL'])
        ->and((new App\Models\PlanGenerator(['upgrade_billing_frequency' => 'MENSUAL']))->upgradeBillingFrequencies())->toBe(['MENSUAL'])
        ->and((new App\Models\PlanGenerator)->upgradeBillingFrequencies())->toBe(['ANUAL']);
});

it('la pantalla del generador muestra las formas de pago y cómo lo verá el cliente', function (): void {
    DB::beginTransaction();

    try {
        $generator = App\Models\PlanGenerator::query()->whereHas('rateRows')->orderByDesc('id')->first();

        if (! $generator instanceof App\Models\PlanGenerator) {
            test()->markTestSkipped('No hay generadores con tarifas en la base para probar la pantalla.');
        }

        $options = App\Support\Upgrades\UpgradePricing::coverageOptions();
        $first = reset($options);
        $coverageId = is_array($first) ? array_key_first($first) : array_key_first($options);
        App\Support\PlanGenerators\PlanGeneratorUpgrades::sync($generator, [['line_key' => (string) Illuminate\Support\Str::uuid(), 'upgrade_coverage_id' => $coverageId, 'excluded_ranges' => [], 'manual_ranges' => []]]);
        $generator->forceFill(['upgrade_billing_frequencies' => ['ANUAL', 'TRIMESTRAL']])->save();

        Filament\Facades\Filament::setCurrentPanel('business');
        test()->actingAs(App\Models\User::factory()->make([
            'id' => 999_999_091,
            'name' => 'QA FORMA DE PAGO',
            'email' => 'qa.formapago@tudrencasa.com',
            'status' => 'ACTIVO',
            'departament' => ['SUPERADMIN', 'NEGOCIOS'],
        ]));

        Livewire\Livewire::test(App\Filament\Business\Resources\PlanGenerators\Pages\EditPlanGenerator::class, ['record' => $generator->getKey()])
            ->assertSet('data.upgrade_billing_frequencies', ['ANUAL', 'TRIMESTRAL'])
            ->assertSee('Forma de pago de los upgrades')
            ->assertSee('Así lo verá el cliente')
            ->assertSee('Repartido en cada cuota')
            ->assertSee('4 cuotas de');
    } finally {
        DB::rollBack();
    }
});
