<?php

declare(strict_types=1);

$basePath = dirname(__DIR__, 2);

it('expone la matriz de negociacion en empresas aliadas', function () use ($basePath): void {
    $resource = file_get_contents($basePath.'/app/Filament/Business/Resources/WhiteCompanies/WhiteCompanyResource.php');
    $manager = file_get_contents($basePath.'/app/Filament/Business/Resources/WhiteCompanies/RelationManagers/NegotiatedFeesRelationManager.php');
    $model = file_get_contents($basePath.'/app/Models/WhiteCompany.php');

    expect($resource)
        ->toContain('NegotiatedFeesRelationManager::class');

    expect($model)
        ->toContain('function negotiatedFees(): HasMany');

    expect($manager)
        ->toContain("protected static string \$relationship = 'negotiatedFees'")
        ->toContain("TextInput::make('sale_price')")
        ->toContain("TextInput::make('neta')")
        ->toContain("Select::make('fee_id')")
        ->toContain("Repeater::make('items')")
        ->toContain("->addActionLabel('Añadir tarifa')")
        ->toContain('Cargar matriz de negociación')
        ->toContain('WhiteCompanyCatalogFeeOptions::forCompany')
        ->toContain('WhiteCompanyCatalogFeeOptions::matching')
        ->toContain('WhiteCompanyNegotiatedFeesBulkCreator::createForCompany')
        ->toContain('createAnother(false)')
        ->toContain('disableOptionsWhenSelectedInSiblingRepeaterItems')
        ->toContain('MATRIZ DE NEGOCIACIÓN')
        ->toContain('lte')
        ->toContain('canViewForRecord')
        ->toContain('BusinessFilamentActionAccess::userCan')
        ->toContain('MANAGE_WHITE_COMPANY_NEGOTIATED_FEES');
});

it('agrega snapshot de venta y neta en afiliaciones', function () use ($basePath): void {
    $affiliation = file_get_contents($basePath.'/app/Models/Affiliation.php');
    $migration = file_get_contents($basePath.'/database/migrations/2026_08_13_202120_add_white_company_rate_snapshot_to_affiliations_table.php');

    expect($affiliation)
        ->toContain("'white_company_sale_price'")
        ->toContain("'white_company_neta'")
        ->toContain("'white_company_fee_id'");

    expect($migration)
        ->toContain("decimal('white_company_sale_price'")
        ->toContain("decimal('white_company_neta'");
});
