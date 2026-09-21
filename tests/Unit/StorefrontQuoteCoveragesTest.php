<?php

declare(strict_types=1);

use App\Enums\PlanPricingMode;
use App\Models\Coverage;
use App\Models\DetailIndividualQuote;
use App\Models\Plan;
use App\Support\Storefront\StorefrontQuoteCoverages;
use App\Support\Storefront\StorefrontQuoteFrequency;

function storefrontCoveragesPath(string $path): string
{
    return dirname(__DIR__, 2).'/'.ltrim($path, '/');
}

it('lista coberturas de la cotizacion y suma la seleccion', function (): void {
    $coberturas = new Plan(['pricing_mode' => PlanPricingMode::Coberturas]);
    $paquete = new Plan(['pricing_mode' => PlanPricingMode::Paquete]);

    $lowCoverage = new Coverage(['price' => 10000]);
    $lowCoverage->id = 1;
    $highCoverage = new Coverage(['price' => 25000]);
    $highCoverage->id = 2;

    $low = new DetailIndividualQuote([
        'coverage_id' => 1,
        'subtotal_anual' => 240,
        'total_persons' => 2,
    ]);
    $low->setRelation('coverage', $lowCoverage);

    $high = new DetailIndividualQuote([
        'coverage_id' => 2,
        'subtotal_anual' => 480,
        'total_persons' => 2,
    ]);
    $high->setRelation('coverage', $highCoverage);

    $details = collect([$low, $high]);
    $options = StorefrontQuoteCoverages::options($details, $coberturas);

    expect($options)->toHaveCount(2)
        ->and($options[0]['id'])->toBe(1)
        ->and($options[0]['label'])->toBe('Cobertura US$ 10.000')
        ->and($options[0]['annual'])->toBe(240.0)
        ->and($options[1]['id'])->toBe(2)
        ->and(StorefrontQuoteCoverages::needsSelection($coberturas, $details))->toBeTrue()
        ->and(StorefrontQuoteCoverages::needsSelection($paquete, $details))->toBeFalse()
        ->and(StorefrontQuoteCoverages::selectionLabel($options, [1, 2]))
        ->toBe('Cobertura US$ 10.000 · Cobertura US$ 25.000')
        ->and(StorefrontQuoteFrequency::paymentFromDetails($details, $coberturas, 'anual', [1, 2]))
        ->toMatchArray([
            'has_amount' => true,
            'amount' => 720.0,
            'amount_prefix' => '',
            'amount_label' => 'US$ 720',
            'annual_total' => 720.0,
        ])
        ->and(StorefrontQuoteFrequency::paymentFromDetails($details, $coberturas, 'semestral', [1]))
        ->toMatchArray([
            'amount' => 120.0,
            'annual_total' => 240.0,
            'amount_period' => 'al semestre',
        ])
        ->and(StorefrontQuoteFrequency::paymentFromDetails($details, $coberturas))
        ->toMatchArray([
            'amount' => 240.0,
            'amount_prefix' => 'Desde',
        ]);
});

it('codifica la seleccion de coberturas para el resto del flujo', function (): void {
    expect(StorefrontQuoteCoverages::encode([2, 1, 1, 0]))->toBe('1,2')
        ->and(StorefrontQuoteCoverages::decode('2, 1, 9', [1, 2]))->toBe([1, 2])
        ->and(StorefrontQuoteCoverages::append(['code' => 'COT-1'], [2, 1]))
        ->toMatchArray(['code' => 'COT-1', 'c' => '1,2'])
        ->and(StorefrontQuoteCoverages::append(['code' => 'COT-1'], []))
        ->toBe(['code' => 'COT-1'])
        ->and(StorefrontQuoteCoverages::sessionKey('COT-1'))
        ->toBe('storefront.quote.coverages.COT-1');
});

it('el tap de una cotizacion abre coberturas y conserva la seleccion hasta el comprobante', function (): void {
    $coverages = file_get_contents(storefrontCoveragesPath('resources/views/livewire/volt/app/quote-coverages.blade.php'));
    $frequency = file_get_contents(storefrontCoveragesPath('resources/views/livewire/volt/app/quote-frequency.blade.php'));
    $pay = file_get_contents(storefrontCoveragesPath('resources/views/livewire/volt/app/quote-pay.blade.php'));
    $receipt = file_get_contents(storefrontCoveragesPath('resources/views/livewire/volt/app/quote-receipt.blade.php'));
    $result = file_get_contents(storefrontCoveragesPath('resources/views/livewire/volt/app/quote-result.blade.php'));
    $index = file_get_contents(storefrontCoveragesPath('app/Support/Storefront/StorefrontQuotesIndex.php'));
    $nav = file_get_contents(storefrontCoveragesPath('app/Support/Storefront/StorefrontNav.php'));
    $routes = file_get_contents(storefrontCoveragesPath('routes/storefront.php'));
    $css = file_get_contents(storefrontCoveragesPath('resources/css/storefront.css'));

    expect($coverages)
        ->toContain('¿Qué coberturas quieres afiliar?')
        ->toContain('wire:click="toggle')
        ->toContain('totals()')
        ->toContain('sf-cov__dock')
        ->toContain('sf-cov__scroll')
        ->toContain('Total anual de tu selección')
        ->toContain('Elige al menos una cobertura')
        ->toContain('storefront.quote.frequency')
        ->and($frequency)->toContain('StorefrontQuoteCoverages::guard')
        ->and($frequency)->toContain('coverages_label')
        ->and($pay)->toContain('StorefrontQuoteCoverages::guard')
        ->and($pay)->toContain('coverages_label')
        ->and($receipt)->toContain('StorefrontQuoteCoverages::guard')
        ->and($receipt)->toContain('coverages_label')
        ->and($result)->toContain("route('storefront.quote.coverages'")
        ->and($result)->toContain('StorefrontQuoteCoverages::needsSelection')
        ->and($index)->toContain("'storefront.quote.coverages'")
        ->and($index)->toContain('StorefrontQuoteCoverages::needsSelection')
        ->and($nav)->toContain("'storefront.quote.coverages' => ''")
        ->and($nav)->toContain("'label' => 'Cambiar coberturas'")
        ->and($routes)->toContain('volt.app.quote-coverages')
        ->and($routes)->toContain("->name('storefront.quote.coverages')")
        ->and($css)->toContain('.sf-cov__choice')
        ->and($css)->toContain('.sf-cov__scroll')
        ->and($css)->toContain('body:has(.sf-cov)')
        ->and($css)->toContain('.sf-cov__total-amount')
        ->and($css)->toContain('.sf-cov__dock::before')
        ->and($css)->toContain('border-radius: 1.55rem 1.55rem 0 0')
        ->and($css)->toContain('padding-left: 0')
        ->and($css)->toContain('.sf-pay__coverages');
});
