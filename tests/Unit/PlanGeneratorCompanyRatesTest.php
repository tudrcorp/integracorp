<?php

declare(strict_types=1);

use App\Support\PlanGenerators\PlanGeneratorCompanyRates;

it('calcula montos según frecuencia de pago del plan generado', function (): void {
    $dataRecord = [
        'subtotal_anual' => 1200.0,
        'subtotal_biannual' => 600.0,
        'subtotal_quarterly' => 300.0,
        'subtotal_monthly' => 100.0,
    ];

    expect(PlanGeneratorCompanyRates::amountsFor('ANUAL', $dataRecord))
        ->toMatchArray(['fee_anual' => 1200.0, 'total_amount' => 1200.0]);

    expect(PlanGeneratorCompanyRates::amountsFor('SEMESTRAL', $dataRecord))
        ->toMatchArray(['fee_anual' => 1200.0, 'total_amount' => 600.0]);

    expect(PlanGeneratorCompanyRates::amountsFor('TRIMESTRAL', $dataRecord))
        ->toMatchArray(['fee_anual' => 1200.0, 'total_amount' => 300.0]);

    expect(PlanGeneratorCompanyRates::amountsFor('MENSUAL', $dataRecord))
        ->toMatchArray(['fee_anual' => 1200.0, 'total_amount' => 100.0]);
});

it('incluye mensual cuando la cotización tiene total mensual', function (): void {
    $payload = [
        'plan' => ['include_monthly_total' => true],
        'data_records' => [],
    ];

    expect(PlanGeneratorCompanyRates::paymentFrequencyOptions($payload))
        ->toHaveKeys(['ANUAL', 'SEMESTRAL', 'TRIMESTRAL', 'MENSUAL']);
});

it('expone opciones de columnas desde data_records del plan', function (): void {
    $payload = [
        'data_records' => [
            ['column_key' => 'col_a', 'header_label' => 'Plan Oro'],
            ['column_key' => 'col_b', 'header_label' => 'Plan Plata'],
        ],
    ];

    expect(PlanGeneratorCompanyRates::columnOptions($payload))
        ->toBe([
            'col_a' => 'Plan Oro',
            'col_b' => 'Plan Plata',
        ]);

    expect(PlanGeneratorCompanyRates::dataRecordForColumn($payload, 'col_b')['header_label'] ?? null)
        ->toBe('Plan Plata');
});

it('registro de empresa incluye selección de plan y frecuencia de pago', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/PlanGenerators/Schemas/RegisterCompanyForm.php');
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/PlanGenerators/Pages/RegisterCompany.php');
    $session = file_get_contents(dirname(__DIR__, 2).'/app/Support/PlanGenerators/PlanGeneratorPreAffiliationSession.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/PlanGenerators/Pages/ViewPlanGenerator.php');

    expect($session)
        ->toContain("TYPE_NEW_BUSINESS = 'new_business'")
        ->toContain('TYPE_CORPORATE, self::TYPE_NEW_BUSINESS');

    expect($view)
        ->toContain('PlanGeneratorPreAffiliationSession::TYPE_NEW_BUSINESS');

    expect($form)
        ->toContain('Plan, frecuencia y tarifas')
        ->toContain('plan_generator_column_key')
        ->toContain('payment_frequency')
        ->toContain('fee_anual')
        ->toContain('total_amount')
        ->toContain('PlanGeneratorCompanyRates::syncAmounts');

    expect($page)
        ->toContain('ensurePlanGeneratorSession')
        ->toContain('PlanGeneratorPreAffiliationSession::forget()')
        ->toContain('payment_frequency');
});
