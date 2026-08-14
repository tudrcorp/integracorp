<?php

declare(strict_types=1);

use App\Models\Affiliation;
use App\Models\User;
use App\Support\AffiliationWhiteCompany;

it('resalta la fila cuando la afiliacion tiene empresa aliada', function (): void {
    $record = (new Affiliation)->forceFill([
        'code' => 'TDEC-IND-000391',
        'white_company_id' => 12,
    ]);

    expect(AffiliationWhiteCompany::belongsToWhiteCompany($record))->toBeTrue()
        ->and(AffiliationWhiteCompany::recordRowClasses($record, ['bg-emerald-50']))
        ->toBe(AffiliationWhiteCompany::RECORD_ROW_CLASSES);
});

it('resalta la fila cuando el usuario de agencia tiene empresa aliada', function (): void {
    $record = (new Affiliation)->forceFill([
        'code' => 'TDEC-IND-000391',
        'code_agency' => 'TDG-101',
    ]);
    $record->setRelation('whiteCompanyUser', (new User)->forceFill([
        'code_agency' => 'TDG-101',
        'white_company_id' => 12,
    ]));

    expect(AffiliationWhiteCompany::belongsToWhiteCompany($record))->toBeTrue()
        ->and(AffiliationWhiteCompany::recordRowClasses($record))->toBe(AffiliationWhiteCompany::RECORD_ROW_CLASSES);
});

it('conserva las clases de fila cuando no hay empresa aliada', function (): void {
    $record = (new Affiliation)->forceFill([
        'code' => 'TDEC-IND-000001',
        'white_company_id' => null,
    ]);
    $record->setRelation('whiteCompanyUser', null);

    expect(AffiliationWhiteCompany::belongsToWhiteCompany($record))->toBeFalse()
        ->and(AffiliationWhiteCompany::recordRowClasses($record, ['border-l-4 border-emerald-400/80']))
        ->toBe(['border-l-4 border-emerald-400/80']);
});

it('expone filtro y resaltado de empresas aliadas en tablas de afiliaciones', function (): void {
    $business = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Tables/AffiliationsTable.php');
    $administration = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/Tables/AffiliationsTable.php');
    $sales = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Tables/SalesTable.php');
    $helper = file_get_contents(dirname(__DIR__, 2).'/app/Support/AffiliationWhiteCompany.php');

    expect($business)
        ->toContain("'whiteCompanyUser'")
        ->toContain('AffiliationWhiteCompany::tableFilter()')
        ->toContain('AffiliationWhiteCompany::recordRowClasses')
        ->not->toContain('AffiliationWhiteCompany::applySelect');

    expect($administration)
        ->toContain("'whiteCompanyUser'")
        ->toContain('AffiliationWhiteCompany::tableFilter()')
        ->toContain('AffiliationWhiteCompany::recordRowClasses')
        ->not->toContain('AffiliationWhiteCompany::applySelect');

    expect($sales)
        ->toContain('AffiliationWhiteCompany::tableFilter()');

    expect($helper)
        ->not->toContain('addSelect')
        ->toContain('fi-affiliation-white-company');

    expect(file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css'))
        ->toContain('.fi-ta-row.fi-affiliation-white-company')
        ->toContain('rgb(91 33 182 / 0.72)');
});
