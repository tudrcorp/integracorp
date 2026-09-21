<?php

declare(strict_types=1);

use App\Enums\PlanPricingMode;
use App\Models\AgeRange;
use App\Models\Coverage;
use App\Models\DetailIndividualQuote;
use App\Models\IndividualQuote;
use App\Models\Plan;
use App\Support\Quotes\InteractiveIndividualQuoteView;

function interactiveQuoteDetail(
    int $ageRangeId,
    string $range,
    int $ageInit,
    int $persons,
    float $fee,
    ?int $coverageId = null,
    ?float $coveragePrice = null,
): DetailIndividualQuote {
    $detail = new DetailIndividualQuote([
        'age_range_id' => $ageRangeId,
        'coverage_id' => $coverageId,
        'total_persons' => $persons,
        'fee' => $fee,
        'subtotal_anual' => $fee * $persons,
        'subtotal_biannual' => ($fee * $persons) / 2,
        'subtotal_quarterly' => ($fee * $persons) / 4,
    ]);

    $ageRange = new AgeRange([
        'range' => $range,
        'age_init' => $ageInit,
        'age_end' => $ageInit + 10,
    ]);
    $ageRange->id = $ageRangeId;
    $detail->setRelation('ageRange', $ageRange);

    if ($coverageId !== null) {
        $coverage = new Coverage(['price' => $coveragePrice ?? 0]);
        $coverage->id = $coverageId;
        $detail->setRelation('coverage', $coverage);
    } else {
        $detail->setRelation('coverage', null);
    }

    return $detail;
}

it('el plan paquete muestra todos los rangos cotizados y el total anual', function (): void {
    $plan = new Plan([
        'description' => 'INICIAL',
        'pricing_mode' => PlanPricingMode::Paquete,
    ]);
    $plan->id = 1;

    $quote = new IndividualQuote([
        'code' => 'COT-IND-0001',
        'full_name' => 'GUSTAVO CAMACHO',
        'created_by' => 'PWA PUBLICO',
        'plan' => 1,
    ]);

    $view = InteractiveIndividualQuoteView::from($quote, $plan, collect([
        interactiveQuoteDetail(10, '0-17', 0, 1, 80.0),
        interactiveQuoteDetail(11, '18-59', 18, 2, 160.0),
    ]));

    expect($view['mode'])->toBe('package')
        ->and($view['plan_title'])->toBe('Plan Inicial')
        ->and($view['client_name'])->toBe('Gustavo Camacho')
        ->and($view['agent_label'])->toBe('Tu Dr En Casa')
        ->and($view['persons'])->toBe(3)
        ->and($view['headline'])->toBe('US$ 400 al año')
        ->and($view['ranges'])->toHaveCount(2)
        ->and($view['ranges'][0]['age_label'])->toBe('0 a 17 años')
        ->and($view['ranges'][0]['cells'][0]['annual'])->toBe(80.0)
        ->and($view['ranges'][1]['age_label'])->toBe('18 a 59 años')
        ->and($view['ranges'][1]['persons'])->toBe(2)
        ->and($view['ranges'][1]['cells'][0]['annual'])->toBe(320.0)
        ->and($view['frequencies'][0]['label'])->toBe('Anual')
        ->and($view['frequencies'][0]['amount'])->toBe(400.0)
        ->and($view['options'])->toHaveCount(1)
        ->and($view['options'][0]['key'])->toBe('package')
        ->and($view['default_coverage_key'])->toBe('package');
});

it('el plan con coberturas ordena montos y suma cada cobertura en todos los rangos', function (): void {
    $plan = new Plan([
        'description' => 'IDEAL',
        'pricing_mode' => PlanPricingMode::Coberturas,
    ]);
    $plan->id = 2;

    $quote = new IndividualQuote([
        'code' => 'COT-IND-0002',
        'full_name' => 'SD',
        'created_by' => 'ANA PEREZ',
        'plan' => 2,
    ]);

    $view = InteractiveIndividualQuoteView::from($quote, $plan, collect([
        interactiveQuoteDetail(20, '18-59', 18, 1, 200.0, 5, 10000),
        interactiveQuoteDetail(20, '18-59', 18, 1, 120.0, 4, 5000),
        interactiveQuoteDetail(21, '0-17', 0, 1, 80.0, 4, 5000),
        interactiveQuoteDetail(21, '0-17', 0, 1, 140.0, 5, 10000),
    ]));

    expect($view['mode'])->toBe('coverages')
        ->and($view['agent_label'])->toBe('Ana Perez')
        ->and($view['coverages'])->toHaveCount(2)
        ->and($view['coverages'][0]['label'])->toBe('Cobertura US$ 5.000')
        ->and($view['coverages'][0]['annual'])->toBe(200.0)
        ->and($view['coverages'][1]['label'])->toBe('Cobertura US$ 10.000')
        ->and($view['coverages'][1]['annual'])->toBe(340.0)
        ->and($view['headline'])->toBe('Desde US$ 200 al año')
        ->and($view['ranges'])->toHaveCount(2)
        ->and($view['ranges'][0]['age_label'])->toBe('0 a 17 años')
        ->and($view['ranges'][0]['cells'])->toHaveCount(2)
        ->and($view['default_coverage_key'])->toBe($view['coverages'][0]['key']);
});

it('la vista interactiva de costos no lista beneficios', function (): void {
    $blade = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/volt/in/individual_quote.blade.php');

    expect($blade)
        ->toContain('iq-quote')
        ->toContain('Cómo se calcula')
        ->toContain('Rangos de edad')
        ->toContain('Formas de pago')
        ->toContain('Descargar cotización')
        ->and($blade)->not->toContain('header_inicial')
        ->and($blade)->not->toContain('interative_quote_ideal_especial')
        ->and($blade)->not->toContain('BENEFICIOS PLAN')
        ->and($blade)->not->toContain('BenefitPlan');
});
