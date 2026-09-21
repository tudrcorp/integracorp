<?php

declare(strict_types=1);

use App\Enums\PlanPricingMode;
use App\Models\DetailIndividualQuote;
use App\Models\Plan;
use App\Support\Storefront\StorefrontQuotesIndex;

function storefrontQuotesPath(string $path): string
{
    return dirname(__DIR__, 2).'/'.ltrim($path, '/');
}

it('las cotizaciones de la pwa quedan ligadas al usuario que las generó', function (): void {
    $creator = file_get_contents(storefrontQuotesPath('app/Support/Storefront/StorefrontQuoteCreator.php'));
    $model = file_get_contents(storefrontQuotesPath('app/Models/IndividualQuote.php'));
    $index = file_get_contents(storefrontQuotesPath('app/Support/Storefront/StorefrontQuotesIndex.php'));
    $migration = file_get_contents(storefrontQuotesPath('database/migrations/2026_09_04_111612_add_storefront_user_id_to_individual_quotes_table.php'));

    expect($creator)
        ->toContain('storefront_user_id')
        ->toContain('$actingUser instanceof User')
        ->and($model)->toContain("'storefront_user_id'")
        ->and($index)->toContain("'storefront.quote.coverages'")
        ->and($index)->toContain('StorefrontQuoteCoverages::needsSelection')
        ->and($index)->not->toContain("where('agent_id'")
        ->and($migration)->toContain('storefront_user_id')
        ->and($migration)->toContain('individual_quotes_storefront_user_id_index');
});

it('el listado busca por codigo nombre correo telefono y estado', function (): void {
    $index = file_get_contents(storefrontQuotesPath('app/Support/Storefront/StorefrontQuotesIndex.php'));

    expect($index)
        ->toContain("orWhere('code', 'like'")
        ->toContain("orWhere('full_name', 'like'")
        ->toContain("orWhere('email', 'like'")
        ->toContain("orWhere('phone', 'like'")
        ->toContain("orWhere('status', 'like'")
        ->toContain("whereIn('status', ['PRE-APROBADA', 'APROBADA'])")
        ->toContain("whereIn('status', ['ANULADA', 'VENCIDA'])")
        ->toContain('plansFor')
        ->toContain('paymentFromDetails')
        ->toContain('PER_PAGE = 8');
});

it('traduce el estado de la cotizacion a un texto corto', function (): void {
    expect(StorefrontQuotesIndex::statusLabel('PRE-APROBADA'))->toBe('Lista')
        ->and(StorefrontQuotesIndex::statusTone('PRE-APROBADA'))->toBe('ok')
        ->and(StorefrontQuotesIndex::statusLabel('APROBADA'))->toBe('Aprobada')
        ->and(StorefrontQuotesIndex::statusTone('ANULADA'))->toBe('warn')
        ->and(StorefrontQuotesIndex::statusLabel('VENCIDA'))->toBe('Vencida')
        ->and(StorefrontQuotesIndex::statusLabel(''))->toBe('En proceso')
        ->and(StorefrontQuotesIndex::normalizeSearch('  COT  IND  '))->toBe('COT IND')
        ->and(StorefrontQuotesIndex::normalizeStatus('READY'))->toBe('ready')
        ->and(StorefrontQuotesIndex::normalizeStatus('nope'))->toBe('all');
});

it('la pantalla de mis cotizaciones permite ubicar y reabrir cada propuesta', function (): void {
    $page = file_get_contents(storefrontQuotesPath('resources/views/livewire/volt/app/quotes.blade.php'));
    $css = file_get_contents(storefrontQuotesPath('resources/css/storefront.css'));
    $routes = file_get_contents(storefrontQuotesPath('routes/storefront.php'));
    $amount = file_get_contents(storefrontQuotesPath('resources/views/storefront/partials/quote-amount.blade.php'));

    expect($page)
        ->toContain('Mis cotizaciones')
        ->toContain('wire:model.live.debounce.300ms="search"')
        ->toContain("setStatus('ready')")
        ->toContain('clearFilters')
        ->toContain('pay_url')
        ->toContain('Ver propuesta')
        ->toContain('sf-quote-card')
        ->toContain('storefront.partials.quote-plan-row')
        ->toContain('storefront.home')
        ->toContain('Aún no tienes cotizaciones')
        ->toContain('No hay coincidencias')
        ->not->toContain('<dt>Grupo</dt>')
        ->not->toContain('<dt>Teléfono</dt>')
        ->not->toContain('<dt>Correo</dt>')
        ->and($amount)->toContain('sf-quote-card__amount-value')
        ->and($amount)->toContain('amount_period')
        ->and($css)->toContain('.sf-quotes')
        ->and($css)->toContain('.sf-quote-card')
        ->and($css)->toContain('.sf-quote-card__row')
        ->and($css)->toContain('--sf-amount')
        ->and($css)->toContain('.sf-quote-card__amount-value')
        ->and($css)->toContain('.sf-quotes__search')
        ->and($css)->toContain('.sf-quotes__filter.is-on')
        ->and($routes)->toContain("Volt::route('/cotizaciones', 'volt.app.quotes')")
        ->and($routes)->toContain("->name('storefront.quotes')");
});

it('el monto de la tarjeta suma el paquete y muestra el menor total en coberturas', function (): void {
    $paquete = new Plan(['pricing_mode' => PlanPricingMode::Paquete]);
    $coberturas = new Plan(['pricing_mode' => PlanPricingMode::Coberturas]);

    $lineA = new DetailIndividualQuote(['subtotal_anual' => 120, 'coverage_id' => null, 'total_persons' => 1]);
    $lineB = new DetailIndividualQuote(['subtotal_anual' => 80, 'coverage_id' => null, 'total_persons' => 1]);
    $coverageLow = new DetailIndividualQuote(['subtotal_anual' => 240, 'coverage_id' => 1, 'total_persons' => 1]);
    $coverageHigh = new DetailIndividualQuote(['subtotal_anual' => 480, 'coverage_id' => 2, 'total_persons' => 1]);

    expect(StorefrontQuotesIndex::paymentFromDetails(collect([$lineA, $lineB]), $paquete))
        ->toMatchArray([
            'has_amount' => true,
            'amount' => 200.0,
            'amount_label' => 'US$ 200',
            'amount_prefix' => '',
            'amount_period' => 'al año',
        ])
        ->and(StorefrontQuotesIndex::paymentFromDetails(collect([$coverageLow, $coverageHigh]), $coberturas))
        ->toMatchArray([
            'has_amount' => true,
            'amount' => 240.0,
            'amount_label' => 'US$ 240',
            'amount_prefix' => 'Desde',
            'amount_period' => 'al año',
        ])
        ->and(StorefrontQuotesIndex::paymentFromDetails(collect([$coverageLow]), $coberturas))
        ->toMatchArray([
            'has_amount' => true,
            'amount' => 240.0,
            'amount_label' => 'US$ 240',
            'amount_prefix' => '',
            'amount_period' => 'al año',
        ])
        ->and(StorefrontQuotesIndex::paymentFromDetails(collect(), $paquete)['has_amount'])
        ->toBeFalse();
});

it('el total de personas se cuenta una vez por rango de edad', function (): void {
    $rangeACoverage1 = new DetailIndividualQuote(['age_range_id' => 10, 'total_persons' => 3, 'coverage_id' => 1]);
    $rangeACoverage2 = new DetailIndividualQuote(['age_range_id' => 10, 'total_persons' => 3, 'coverage_id' => 2]);
    $rangeACoverage3 = new DetailIndividualQuote(['age_range_id' => 10, 'total_persons' => 3, 'coverage_id' => 3]);
    $rangeBCoverage1 = new DetailIndividualQuote(['age_range_id' => 20, 'total_persons' => 2, 'coverage_id' => 1]);
    $rangeBCoverage2 = new DetailIndividualQuote(['age_range_id' => 20, 'total_persons' => 2, 'coverage_id' => 2]);
    $rangeBCoverage3 = new DetailIndividualQuote(['age_range_id' => 20, 'total_persons' => 2, 'coverage_id' => 3]);

    expect(StorefrontQuotesIndex::personsFromDetails(collect([
        $rangeACoverage1,
        $rangeACoverage2,
        $rangeACoverage3,
        $rangeBCoverage1,
        $rangeBCoverage2,
        $rangeBCoverage3,
    ])))->toBe(5)
        ->and(StorefrontQuotesIndex::personsLabel(5))->toBe('5 personas')
        ->and(StorefrontQuotesIndex::personsLabel(1))->toBe('1 persona')
        ->and(StorefrontQuotesIndex::personsFromDetails(collect([
            new DetailIndividualQuote(['age_range_id' => null, 'total_persons' => 2]),
            new DetailIndividualQuote(['age_range_id' => null, 'total_persons' => 1]),
        ])))->toBe(3);
});
