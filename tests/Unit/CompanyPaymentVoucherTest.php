<?php

declare(strict_types=1);

use App\Support\Companies\CompanyPaymentAnnualTotalResolver;
use App\Support\PlanGenerators\PlanGeneratorGroupTotalCalculator;

it('resuelve tarifa anual desde cotización asociada al negocio', function (): void {
    $columns = [
        [
            'column_key' => 'col_a',
            'header_label' => 'Plan A',
            'sort_order' => 0,
        ],
    ];

    $rateRows = [
        'row_1' => [
            'population' => 10,
            'cells' => [
                'col_a' => ['rate_amount' => '100'],
            ],
        ],
        'row_2' => [
            'population' => 5,
            'cells' => [
                'col_a' => ['rate_amount' => '200'],
            ],
        ],
    ];

    $expected = PlanGeneratorGroupTotalCalculator::annualTotalForColumn('col_a', $rateRows);

    expect($expected)->toBe(2000.0);
});

it('incluye acción de comprobante de pago en tabla y vista de nuevos negocios', function (): void {
    $tableActions = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Companies/Actions/CompanyTableActions.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Companies/Tables/CompaniesTable.php');
    $viewPage = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Companies/Pages/ViewCompany.php');

    expect($tableActions)
        ->toContain('uploadPaymentVoucherAction')
        ->toContain('CompanyPaymentVoucherForm::schema')
        ->toContain('CompanyPaymentUploadService::upload')
        ->toContain('Comprobante de Pago');

    expect($table)
        ->toContain('CompanyTableActions::uploadPaymentVoucherAction');

    expect($viewPage)
        ->toContain('CompanyTableActions::uploadPaymentVoucherAction');
});

it('servicio de carga registra auditoría de comprobante', function (): void {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyPaymentUploadService.php');

    expect($service)
        ->toContain('AUDIT_BUSINESS_COMPANY_PAYMENT_VOUCHER_UPLOADED')
        ->toContain('AUDIT_BUSINESS_COMPANY_PAYMENT_VOUCHER_UPLOAD_FAILED')
        ->toContain('paidMemberships()');
});

it('formulario de comprobante reutiliza esquema compartido de afiliaciones', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyPaymentVoucherForm.php');
    $schema = file_get_contents(dirname(__DIR__, 2).'/app/Support/PaymentVoucherFormSchema.php');

    expect($form)
        ->toContain('PaymentVoucherFormSchema::components')
        ->toContain('CompanyPaymentAnnualTotalResolver::resolve');

    expect($schema)
        ->toContain('payment_adjustment_percentage')
        ->toContain('LINK DE PAGO')
        ->toContain('AffiliationPaymentBcvRateCalculator');
});

it('resolver muestra texto de ayuda con cotización o mensaje sin plan', function (): void {
    expect(CompanyPaymentAnnualTotalResolver::helperText(new \App\Models\Company([
        'name' => 'Acme',
        'plan_generator_id' => null,
    ])))->toContain('Sin cotización asociada');
});
