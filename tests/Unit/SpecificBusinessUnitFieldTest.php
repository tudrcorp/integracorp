<?php

declare(strict_types=1);

use App\Support\AffiliationAffiliateBusinessContextSynchronizer;

$basePath = dirname(__DIR__, 2);

it('normaliza la unidad de negocio especifica vacia a nulo', function (): void {
    expect(AffiliationAffiliateBusinessContextSynchronizer::normalizeSpecificBusinessUnit(null))->toBeNull()
        ->and(AffiliationAffiliateBusinessContextSynchronizer::normalizeSpecificBusinessUnit(''))->toBeNull()
        ->and(AffiliationAffiliateBusinessContextSynchronizer::normalizeSpecificBusinessUnit('   '))->toBeNull()
        ->and(AffiliationAffiliateBusinessContextSynchronizer::normalizeSpecificBusinessUnit(' Banco X '))
        ->toBe('Banco X');
});

it('agrega el campo de unidad de negocio especifica en formularios de afiliacion', function () use ($basePath): void {
    $individual = file_get_contents($basePath.'/app/Filament/Business/Resources/Affiliations/Schemas/AffiliationForm.php');
    $corporate = file_get_contents($basePath.'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateForm.php');

    expect($individual)
        ->toContain("TextInput::make('specific_business_unit')")
        ->toContain("->label('Unidad de Negocio específica')")
        ->and($corporate)
        ->toContain("TextInput::make('specific_business_unit')")
        ->toContain("->label('Unidad de Negocio específica')");

    $individualUnitPos = strpos($individual, "Select::make('business_unit_id')");
    $individualSpecificPos = strpos($individual, "TextInput::make('specific_business_unit')");
    $corporateUnitPos = strpos($corporate, "Select::make('business_unit_id')");
    $corporateSpecificPos = strpos($corporate, "TextInput::make('specific_business_unit')");

    expect($individualUnitPos)->toBeInt()
        ->and($individualSpecificPos)->toBeInt()
        ->and($individualSpecificPos)->toBeGreaterThan($individualUnitPos)
        ->and($corporateUnitPos)->toBeInt()
        ->and($corporateSpecificPos)->toBeInt()
        ->and($corporateSpecificPos)->toBeGreaterThan($corporateUnitPos);
});

it('sincroniza la unidad de negocio especifica hacia afiliados', function () use ($basePath): void {
    $individualSync = file_get_contents($basePath.'/app/Support/AffiliationAffiliateBusinessContextSynchronizer.php');
    $corporateSync = file_get_contents($basePath.'/app/Support/AffiliationCorporateAffiliateBusinessContextSynchronizer.php');
    $individualForm = file_get_contents($basePath.'/app/Filament/Business/Resources/Affiliations/Schemas/AffiliationForm.php');
    $corporateForm = file_get_contents($basePath.'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateForm.php');

    expect($individualSync)
        ->toContain('mixed $specificBusinessUnit = null')
        ->toContain("'specific_business_unit'")
        ->and($corporateSync)
        ->toContain('mixed $specificBusinessUnit = null')
        ->toContain("'specific_business_unit'")
        ->and($individualForm)
        ->toContain("\$get('specific_business_unit')")
        ->and($corporateForm)
        ->toContain("\$get('specific_business_unit')");
});

it('expone la unidad de negocio especifica en tablas de afiliados y pacientes', function () use ($basePath): void {
    expect(file_get_contents($basePath.'/app/Filament/Operations/Resources/Affiliates/Tables/AffiliatesTable.php'))
        ->toContain("TextColumn::make('affiliation.specific_business_unit')")
        ->toContain("->label('Unidad de negocio específica')");

    expect(file_get_contents($basePath.'/app/Filament/Operations/Resources/AffiliateCorporates/Tables/AffiliateCorporatesTable.php'))
        ->toContain("TextColumn::make('affiliationCorporate.specific_business_unit')")
        ->toContain("->label('Unidad de negocio específica')");

    expect(file_get_contents($basePath.'/app/Filament/Business/Resources/Affiliations/RelationManagers/AffiliatesRelationManager.php'))
        ->toContain("TextColumn::make('specific_business_unit')");

    expect(file_get_contents($basePath.'/app/Filament/Business/Resources/AffiliationCorporates/RelationManagers/CorporateAffiliatesRelationManager.php'))
        ->toContain("TextColumn::make('specific_business_unit')");

    expect(file_get_contents($basePath.'/app/Filament/Operations/Resources/TelemedicinePatients/Tables/TelemedicinePatientsTable.php'))
        ->toContain("TextColumn::make('specific_business_unit')")
        ->toContain("->label('Unidad de negocio específica')");

    expect(file_get_contents($basePath.'/app/Filament/Telemedicina/Resources/TelemedicinePatients/Tables/TelemedicinePatientsTable.php'))
        ->toContain("TextColumn::make('specific_business_unit')")
        ->toContain("->label('Unidad de negocio específica')");

    expect(file_get_contents($basePath.'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php'))
        ->toContain("TextColumn::make('patient_specific_business_unit')")
        ->toContain("->label('Unidad de negocio específica')");
});

it('copia la unidad de negocio especifica al asociar pacientes de telemedicina', function () use ($basePath): void {
    expect(file_get_contents($basePath.'/app/Services/AssociateAffiliateWithTelemedicinePatientService.php'))
        ->toContain("'specific_business_unit'")
        ->and(file_get_contents($basePath.'/app/Services/AssociateAffiliateCorporateWithTelemedicinePatientService.php'))
        ->toContain("'specific_business_unit'");
});

it('define la columna specific_business_unit en la migracion aditiva', function () use ($basePath): void {
    $source = file_get_contents($basePath.'/database/migrations/2026_08_28_000700_add_specific_business_unit_to_affiliations_and_patients_tables.php');

    expect($source)
        ->toContain("'affiliations'")
        ->toContain("'affiliation_corporates'")
        ->toContain("'affiliates'")
        ->toContain("'affiliate_corporates'")
        ->toContain("'telemedicine_patients'")
        ->toContain("string('specific_business_unit')")
        ->toContain("Schema::hasColumn(\$tableName, 'specific_business_unit')");
});

it('incluye specific_business_unit en los modelos de afiliacion afiliados y pacientes', function () use ($basePath): void {
    foreach ([
        '/app/Models/Affiliation.php',
        '/app/Models/AffiliationCorporate.php',
        '/app/Models/Affiliate.php',
        '/app/Models/AffiliateCorporate.php',
        '/app/Models/TelemedicinePatient.php',
    ] as $relativePath) {
        expect(file_get_contents($basePath.$relativePath))
            ->toContain("'specific_business_unit'");
    }
});
