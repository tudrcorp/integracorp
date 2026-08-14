<?php

declare(strict_types=1);

use App\Models\WhiteCompany;
use App\Models\WhiteCompanyPlanLabel;
use App\Support\WhiteCompanies\WhiteCompanyDocumentBrand;

uses(Tests\TestCase::class);

it('usa el nombre comercial del plan cuando la empresa aliada lo configura', function (): void {
    $company = new WhiteCompany(['name' => 'VivePlus']);
    $company->setRelation('planDocuments', collect());
    $company->setRelation('planLabels', collect([
        new WhiteCompanyPlanLabel([
            'plan_id' => 3,
            'display_name' => 'Plan Bienestar',
            'short_label' => 'BIENESTAR',
        ]),
    ]));

    $brand = WhiteCompanyDocumentBrand::fromCompany($company);

    expect($brand->planDisplayName(3, 'PLAN ESPECIAL'))->toBe('Plan Bienestar')
        ->and($brand->planShortLabel(3, 'ESPECIAL'))->toBe('BIENESTAR')
        ->and($brand->planDisplayName(1, 'PLAN INICIAL'))->toBe('PLAN INICIAL')
        ->and($brand->planShortLabel(1, 'INICIAL'))->toBe('INICIAL');
});

it('deriva la etiqueta del carnet si solo hay nombre comercial', function (): void {
    $company = new WhiteCompany(['name' => 'VivePlus']);
    $company->setRelation('planDocuments', collect());
    $company->setRelation('planLabels', collect([
        new WhiteCompanyPlanLabel([
            'plan_id' => 3,
            'display_name' => 'Plan Bienestar',
            'short_label' => null,
        ]),
    ]));

    $brand = WhiteCompanyDocumentBrand::fromCompany($company);

    expect($brand->planShortLabel(3, 'ESPECIAL'))->toBe('BIENESTAR');
});

it('el kit TDEC no traduce el nombre del plan', function (): void {
    $brand = WhiteCompanyDocumentBrand::tdec();

    expect($brand->planDisplayName(3, 'PLAN ESPECIAL'))->toBe('PLAN ESPECIAL')
        ->and($brand->planShortLabel(3, 'ESPECIAL'))->toBe('ESPECIAL');
});
