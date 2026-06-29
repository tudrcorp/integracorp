<?php

declare(strict_types=1);

$basePath = dirname(__DIR__, 2);

it('registra trazas de seguridad en la tabla de empresas aliadas', function () use ($basePath): void {
    $source = file_get_contents($basePath.'/app/Filament/Business/Resources/WhiteCompanies/Tables/WhiteCompaniesTable.php');

    expect($source)
        ->toContain('use App\Support\SecurityAudit;')
        ->toContain('SecurityAudit::log')
        ->toContain("'module' => 'white_companies'")
        ->toContain('AUDIT_BUSINESS_WHITE_COMPANY_UPDATED')
        ->toContain('AUDIT_BUSINESS_WHITE_COMPANIES_BULK_DELETED');
});

it('registra trazas de seguridad al crear una empresa aliada', function () use ($basePath): void {
    $source = file_get_contents($basePath.'/app/Filament/Business/Resources/WhiteCompanies/Pages/CreateWhiteCompany.php');

    expect($source)
        ->toContain('SecurityAudit::log')
        ->toContain('AUDIT_BUSINESS_WHITE_COMPANY_CREATED')
        ->toContain('AUDIT_BUSINESS_WHITE_COMPANY_CREATE_RELATED_FAILED')
        ->toContain("'module' => 'white_companies'");
});

it('registra trazas de seguridad al editar y eliminar una empresa aliada', function () use ($basePath): void {
    $source = file_get_contents($basePath.'/app/Filament/Business/Resources/WhiteCompanies/Pages/EditWhiteCompany.php');

    expect($source)
        ->toContain('SecurityAudit::log')
        ->toContain('AUDIT_BUSINESS_WHITE_COMPANY_UPDATED')
        ->toContain('AUDIT_BUSINESS_WHITE_COMPANY_DELETED')
        ->toContain("'module' => 'white_companies'");
});
