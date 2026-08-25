<?php

declare(strict_types=1);

use App\Models\AffiliateCorporate;
use App\Support\AffiliationCorporates\CorporateAffiliateRelationship;

it('expone las opciones de parentesco del afiliado corporativo', function (): void {
    expect(CorporateAffiliateRelationship::options())
        ->toHaveKey('COLABORADOR')
        ->toHaveKey('TITULAR')
        ->toHaveKey('MADRE')
        ->toHaveKey('PADRE')
        ->toHaveKey('ESPOSA')
        ->toHaveKey('ESPOSO')
        ->toHaveKey('HIJO')
        ->toHaveKey('HIJA')
        ->toHaveKey('OTRO')
        ->and(CorporateAffiliateRelationship::DEFAULT)->toBe('COLABORADOR');
});

it('resuelve la etiqueta de parentesco y el valor del certificado', function (): void {
    expect(CorporateAffiliateRelationship::label('HIJO'))->toBe('Hijo')
        ->and(CorporateAffiliateRelationship::label(null))->toBeNull()
        ->and(CorporateAffiliateRelationship::label(''))->toBeNull()
        ->and(CorporateAffiliateRelationship::forCertificate('hija'))->toBe('HIJA')
        ->and(CorporateAffiliateRelationship::forCertificate(null))->toBe('COLABORADOR')
        ->and(CorporateAffiliateRelationship::forCertificate('DESCONOCIDO'))->toBe('COLABORADOR');
});

it('permite persistir parentesco en el modelo de afiliado corporativo', function (): void {
    expect((new AffiliateCorporate)->getFillable())->toContain('relationship');
});

it('agrega parentesco en datos personales del formulario de edicion corporativa', function (): void {
    $businessForm = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/RelationManagers/CorporateAffiliatesRelationManager.php');
    $administrationForm = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AffiliationCorporates/RelationManagers/CorporateAffiliatesRelationManager.php');
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/AffiliateCorporates/Schemas/AffiliateCorporateInfolist.php');
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_25_082000_add_relationship_to_affiliate_corporates_table.php');

    expect($businessForm)
        ->toContain("Section::make('Datos personales')")
        ->toContain("Select::make('relationship')")
        ->toContain("->label('Parentesco')")
        ->and($administrationForm)
        ->toContain("Select::make('relationship')")
        ->toContain("->label('Parentesco')")
        ->and($infolist)
        ->toContain("TextEntry::make('relationship')")
        ->and($migration)
        ->toContain("Schema::hasColumn('affiliate_corporates', 'relationship')")
        ->toContain("\$table->string('relationship')->nullable()");
});
