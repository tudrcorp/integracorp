<?php

declare(strict_types=1);

$basePath = dirname(__DIR__, 2);

it('agrega el fieldset de Tipo de Afiliación con boton de sincronizar en el formulario individual', function () use ($basePath): void {
    $source = file_get_contents($basePath.'/app/Filament/Business/Resources/Affiliations/Schemas/AffiliationForm.php');

    expect($source)
        ->toContain("Fieldset::make('Tipo de Afiliación')")
        ->toContain("Select::make('affiliation_type')")
        ->toContain("->label('Tipo de Afiliación')")
        ->toContain("'ESTANDARD' => 'ESTANDARD'")
        ->toContain("'VIP' => 'VIP'")
        ->toContain("->default('ESTANDARD')")
        ->toContain("Action::make('syncAffiliateAffiliationType')")
        ->toContain('AffiliationAffiliateTypeSynchronizer::class')
        ->toContain('Sincronizar con afiliados')
        ->toContain('SecurityAudit::log')
        ->toContain('AUDIT_BUSINESS_AFFILIATION_TYPE_SYNCED')
        ->toContain('AUDIT_BUSINESS_AFFILIATION_TYPE_SYNC_FAILED');
});

it('agrega el fieldset de Tipo de Afiliación con boton de sincronizar en el formulario corporativo', function () use ($basePath): void {
    $source = file_get_contents($basePath.'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateForm.php');

    expect($source)
        ->toContain("Fieldset::make('Tipo de Afiliación')")
        ->toContain("Select::make('affiliation_type')")
        ->toContain("->label('Tipo de Afiliación')")
        ->toContain("'ESTANDARD' => 'ESTANDARD'")
        ->toContain("'VIP' => 'VIP'")
        ->toContain("->default('ESTANDARD')")
        ->toContain("Action::make('syncAffiliateCorporateAffiliationType')")
        ->toContain('AffiliationCorporateAffiliateTypeSynchronizer::class')
        ->toContain('Sincronizar con afiliados')
        ->toContain('SecurityAudit::log')
        ->toContain('AUDIT_BUSINESS_AFFILIATION_CORPORATE_TYPE_SYNCED')
        ->toContain('AUDIT_BUSINESS_AFFILIATION_CORPORATE_TYPE_SYNC_FAILED');
});

it('muestra el Tipo de Afiliación en el infolist individual', function () use ($basePath): void {
    $source = file_get_contents($basePath.'/app/Filament/Business/Resources/Affiliations/Schemas/AffiliationInfolist.php');

    expect($source)
        ->toContain("TextEntry::make('affiliation_type')")
        ->toContain("->label('Tipo de afiliación')");
});

it('muestra el Tipo de Afiliación en el infolist corporativo', function () use ($basePath): void {
    $source = file_get_contents($basePath.'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateInfolist.php');

    expect($source)
        ->toContain("TextEntry::make('affiliation_type')")
        ->toContain("->label('Tipo de afiliación')");
});

it('define affiliation_type como fillable en los modelos', function () use ($basePath): void {
    expect(file_get_contents($basePath.'/app/Models/Affiliation.php'))
        ->toContain("'affiliation_type'");

    expect(file_get_contents($basePath.'/app/Models/AffiliationCorporate.php'))
        ->toContain("'affiliation_type'");

    expect(file_get_contents($basePath.'/app/Models/Affiliate.php'))
        ->toContain("'affiliation_type'");

    expect(file_get_contents($basePath.'/app/Models/AffiliateCorporate.php'))
        ->toContain("'affiliation_type'");
});

it('exige el tipo de afiliación para sincronizar afiliados individuales', function (): void {
    $affiliation = new App\Models\Affiliation;
    $synchronizer = new App\Support\AffiliationAffiliateTypeSynchronizer;

    expect(fn (): int => $synchronizer->sync($affiliation, null))
        ->toThrow(InvalidArgumentException::class);

    expect(fn (): int => $synchronizer->sync($affiliation, ''))
        ->toThrow(InvalidArgumentException::class);
});

it('exige el tipo de afiliación para sincronizar afiliados corporativos', function (): void {
    $affiliationCorporate = new App\Models\AffiliationCorporate;
    $synchronizer = new App\Support\AffiliationCorporateAffiliateTypeSynchronizer;

    expect(fn (): int => $synchronizer->sync($affiliationCorporate, null))
        ->toThrow(InvalidArgumentException::class);

    expect(fn (): int => $synchronizer->sync($affiliationCorporate, ''))
        ->toThrow(InvalidArgumentException::class);
});

it('crea la columna affiliation_type en las migraciones de afiliados', function () use ($basePath): void {
    $individual = file_get_contents($basePath.'/database/migrations/2026_06_29_090003_add_affiliation_type_to_affiliates_table.php');
    $corporate = file_get_contents($basePath.'/database/migrations/2026_06_29_090609_add_affiliation_type_to_affiliate_corporates_table.php');

    expect($individual)
        ->toContain("\$table->string('affiliation_type')->default('ESTANDARD')");

    expect($corporate)
        ->toContain("\$table->string('affiliation_type')->default('ESTANDARD')");
});

it('crea la columna affiliation_type con ESTANDARD por defecto en las migraciones', function () use ($basePath): void {
    $individual = file_get_contents($basePath.'/database/migrations/2026_06_29_085139_add_affiliation_type_to_affiliations_table.php');
    $corporate = file_get_contents($basePath.'/database/migrations/2026_06_29_085140_add_affiliation_type_to_affiliation_corporates_table.php');

    expect($individual)
        ->toContain("\$table->string('affiliation_type')->default('ESTANDARD')");

    expect($corporate)
        ->toContain("\$table->string('affiliation_type')->default('ESTANDARD')");
});
