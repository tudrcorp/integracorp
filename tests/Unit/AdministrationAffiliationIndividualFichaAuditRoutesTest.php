<?php

declare(strict_types=1);

it('rutas y controlador de ficha individual registran auditoría esperada', function (): void {
    $controllerPath = dirname(__DIR__, 2).'/app/Http/Controllers/AffiliationFichaPdfController.php';
    $routesPath = dirname(__DIR__, 2).'/routes/web.php';
    $editPagePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/Pages/EditAffiliation.php';

    $controllerContents = file_get_contents($controllerPath);
    $routesContents = file_get_contents($routesPath);
    $editContents = file_get_contents($editPagePath);

    expect($controllerContents)
        ->toContain('AUDIT_ADMINISTRATION_AFFILIATION_INDIVIDUAL_FICHA_VIEWED')
        ->and($controllerContents)->toContain('AUDIT_ADMINISTRATION_AFFILIATION_INDIVIDUAL_FICHA_DOWNLOADED')
        ->and($controllerContents)->toContain('administration.affiliations.ficha.preview')
        ->and($controllerContents)->toContain('administration.affiliations.ficha.download');

    expect($routesContents)
        ->toContain('administration.affiliations.ficha.preview')
        ->and($routesContents)->toContain('administration.affiliations.ficha.download');

    expect($editContents)
        ->toContain('administration.affiliations.ficha.preview')
        ->and($editContents)->toContain('affiliation-ficha-preview-modal');
});
