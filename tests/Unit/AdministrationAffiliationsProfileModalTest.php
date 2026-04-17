<?php

declare(strict_types=1);

it('abre modal de perfil al hacer click en codigo de afiliaciones de administracion', function (): void {
    $individualTablePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/Tables/AffiliationsTable.php';
    $corporateTablePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php';
    $individualViewPath = dirname(__DIR__, 2).'/resources/views/filament/administration/affiliations/affiliation-quick-profile.blade.php';
    $corporateViewPath = dirname(__DIR__, 2).'/resources/views/filament/administration/affiliation-corporates/affiliation-corporate-quick-profile.blade.php';

    $individualTable = file_get_contents($individualTablePath);
    $corporateTable = file_get_contents($corporateTablePath);
    $individualView = file_get_contents($individualViewPath);

    expect(file_exists($individualViewPath))->toBeTrue()
        ->and(file_exists($corporateViewPath))->toBeTrue();

    expect($individualTable)
        ->toContain("TextColumn::make('code')")
        ->toContain("Action::make('view_affiliation_profile')")
        ->toContain('modalHeading(\'Afiliación Individual\')')
        ->toContain('modalWidth(\'5xl\')')
        ->toContain('filament.administration.affiliations.affiliation-quick-profile');

    expect($individualView)
        ->toContain('totalAfiliados')
        ->toContain('Total de afiliados')
        ->toContain('@if ($totalAfiliados > 1)');

    expect($corporateTable)
        ->toContain("TextColumn::make('code')")
        ->toContain("Action::make('view_affiliation_corporate_profile')")
        ->toContain('modalHeading(\'Afiliación Corporativa\')')
        ->toContain('modalWidth(\'5xl\')')
        ->toContain('filament.administration.affiliation-corporates.affiliation-corporate-quick-profile');
});
