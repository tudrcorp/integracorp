<?php

declare(strict_types=1);

it('navega al view en afiliaciones individuales de administracion', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/Tables/AffiliationsTable.php');

    expect($source)
        ->toContain("AffiliationResource::getUrl('view', ['record' => \$record])")
        ->not->toContain('view_affiliation_profile');
});

it('navega al view en afiliaciones corporativas de administracion', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php');

    expect($source)
        ->toContain("AffiliationCorporateResource::getUrl('view', ['record' => \$record])")
        ->not->toContain('view_affiliation_corporate_profile');
});

it('registra paginas view en recursos de afiliaciones de administracion', function (): void {
    $individual = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/AffiliationResource.php');
    $corporate = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AffiliationCorporates/AffiliationCorporateResource.php');

    expect($individual)
        ->toContain('ViewAffiliation::route')
        ->toContain('AffiliationInfolist::configure');
    expect($corporate)
        ->toContain('ViewAffiliationCorporate::route')
        ->toContain('AffiliationCorporateInfolist::configure');
});
