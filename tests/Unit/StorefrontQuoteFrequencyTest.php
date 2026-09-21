<?php

declare(strict_types=1);

use App\Enums\PlanPricingMode;
use App\Models\DetailIndividualQuote;
use App\Models\Plan;
use App\Support\Storefront\StorefrontQuoteFrequency;
use App\Support\Storefront\StorefrontQuotesIndex;

it('normaliza anual semestral y trimestral', function (): void {
    expect(StorefrontQuoteFrequency::normalize('Anual'))->toBe('anual')
        ->and(StorefrontQuoteFrequency::normalize('semetral'))->toBe('semestral')
        ->and(StorefrontQuoteFrequency::normalize('quarterly'))->toBe('trimestral')
        ->and(StorefrontQuoteFrequency::isKnown('semestral'))->toBeTrue()
        ->and(StorefrontQuoteFrequency::isKnown('nope'))->toBeFalse()
        ->and(StorefrontQuoteFrequency::choices())->toHaveCount(3)
        ->and(StorefrontQuoteFrequency::label('semestral'))->toBe('Semestral')
        ->and(StorefrontQuoteFrequency::hint('trimestral'))->toBe('4 pagos al año');
});

it('calcula el pago semestral y trimestral a partir del anual', function (): void {
    $paquete = new Plan(['pricing_mode' => PlanPricingMode::Paquete]);
    $lineA = new DetailIndividualQuote(['subtotal_anual' => 120, 'coverage_id' => null]);
    $lineB = new DetailIndividualQuote(['subtotal_anual' => 80, 'coverage_id' => null]);
    $details = collect([$lineA, $lineB]);

    expect(StorefrontQuotesIndex::paymentFromDetails($details, $paquete, 'semestral'))
        ->toMatchArray([
            'has_amount' => true,
            'amount' => 100.0,
            'amount_label' => 'US$ 100',
            'amount_prefix' => '',
            'amount_period' => 'al semestre',
            'frequency' => 'semestral',
            'installments' => 2,
            'annual_total' => 200.0,
            'breakdown' => '2 pagos de US$ 100 · Total US$ 200 al año',
        ])
        ->and(StorefrontQuotesIndex::paymentFromDetails($details, $paquete, 'trimestral'))
        ->toMatchArray([
            'amount' => 50.0,
            'amount_period' => 'al trimestre',
            'installments' => 4,
            'breakdown' => '4 pagos de US$ 50 · Total US$ 200 al año',
        ]);
});

it('respeta el subtotal semestral guardado cuando existe', function (): void {
    $paquete = new Plan(['pricing_mode' => PlanPricingMode::Paquete]);
    $line = new DetailIndividualQuote([
        'subtotal_anual' => 200,
        'subtotal_biannual' => 90,
        'coverage_id' => null,
    ]);

    expect(StorefrontQuoteFrequency::paymentFromDetails(collect([$line]), $paquete, 'semestral')['amount'])
        ->toBe(90.0);
});
