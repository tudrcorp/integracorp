<?php

declare(strict_types=1);

it('rutas y controlador de ficha corporativa registran auditoría esperada', function (): void {
    $controllerPath = dirname(__DIR__, 2).'/app/Http/Controllers/AffiliationCorporateFichaPdfController.php';
    $routesPath = dirname(__DIR__, 2).'/routes/web.php';
    $editPagePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AffiliationCorporates/Pages/EditAffiliationCorporate.php';
    $viewPagePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AffiliationCorporates/Pages/ViewAffiliationCorporate.php';
    $actionsPath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AffiliationCorporates/Actions/AffiliationCorporateFichaPdfActions.php';

    $controllerContents = file_get_contents($controllerPath);
    $routesContents = file_get_contents($routesPath);
    $editContents = file_get_contents($editPagePath);
    $viewContents = file_get_contents($viewPagePath);
    $actionsContents = file_get_contents($actionsPath);

    expect($controllerContents)
        ->toContain('AUDIT_ADMINISTRATION_AFFILIATION_CORPORATE_FICHA_VIEWED')
        ->and($controllerContents)->toContain('AUDIT_ADMINISTRATION_AFFILIATION_CORPORATE_FICHA_DOWNLOADED')
        ->and($controllerContents)->toContain('administration.affiliation-corporates.ficha.preview')
        ->and($controllerContents)->toContain('administration.affiliation-corporates.ficha.download');

    expect($routesContents)
        ->toContain('administration.affiliation-corporates.ficha.preview')
        ->and($routesContents)->toContain('administration.affiliation-corporates.ficha.download');

    expect($actionsContents)
        ->toContain('administration.affiliation-corporates.ficha.preview')
        ->and($actionsContents)->toContain('affiliation-corporate-ficha-preview-modal');

    expect($editContents)->toContain('AffiliationCorporateFichaPdfActions::printCorporatePdfAction');

    expect($viewContents)->toContain('AffiliationCorporateFichaPdfActions::printCorporatePdfAction');
});
