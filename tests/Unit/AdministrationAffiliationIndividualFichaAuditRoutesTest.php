<?php

declare(strict_types=1);

it('rutas y controlador de ficha individual registran auditoría esperada', function (): void {
    $controllerPath = dirname(__DIR__, 2).'/app/Http/Controllers/AffiliationFichaPdfController.php';
    $routesPath = dirname(__DIR__, 2).'/routes/web.php';
    $editPagePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/Pages/EditAffiliation.php';
    $viewPagePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/Pages/ViewAffiliation.php';
    $actionsPath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/Actions/AffiliationFichaPdfActions.php';

    $controllerContents = file_get_contents($controllerPath);
    $routesContents = file_get_contents($routesPath);
    $editContents = file_get_contents($editPagePath);
    $viewContents = file_get_contents($viewPagePath);
    $actionsContents = file_get_contents($actionsPath);

    expect($controllerContents)
        ->toContain('AUDIT_ADMINISTRATION_AFFILIATION_INDIVIDUAL_FICHA_VIEWED')
        ->and($controllerContents)->toContain('AUDIT_ADMINISTRATION_AFFILIATION_INDIVIDUAL_FICHA_DOWNLOADED')
        ->and($controllerContents)->toContain('administration.affiliations.ficha.preview')
        ->and($controllerContents)->toContain('administration.affiliations.ficha.download');

    expect($routesContents)
        ->toContain('administration.affiliations.ficha.preview')
        ->and($routesContents)->toContain('administration.affiliations.ficha.download');

    expect($actionsContents)
        ->toContain('administration.affiliations.ficha.preview')
        ->and($actionsContents)->toContain('affiliation-ficha-preview-modal');

    expect($editContents)
        ->toContain('AffiliationFichaPdfActions::printIndividualPdfAction')
        ->and($editContents)->toContain('getRelationManagersContentComponent')
        ->and($editContents)->not->toContain('getFormContentComponent');

    expect($viewContents)->toContain('AffiliationFichaPdfActions::printIndividualPdfAction');
});
