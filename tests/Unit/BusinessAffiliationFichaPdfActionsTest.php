<?php

declare(strict_types=1);

it('expone ficha técnica en acciones de afiliación individual de negocios', function (): void {
    $viewPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Pages/ViewAffiliation.php';
    $actionsPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Actions/AffiliationFichaPdfActions.php';
    $routesPath = dirname(__DIR__, 2).'/routes/web.php';
    $controllerPath = dirname(__DIR__, 2).'/app/Http/Controllers/AffiliationFichaPdfController.php';

    $viewContents = file_get_contents($viewPath);
    $actionsContents = file_get_contents($actionsPath);
    $routesContents = file_get_contents($routesPath);
    $controllerContents = file_get_contents($controllerPath);

    expect($viewContents)->toContain('AffiliationFichaPdfActions::printIndividualPdfAction');

    $posEdit = strpos($viewContents, "->label('Editar')");
    $posFicha = strpos($viewContents, 'AffiliationFichaPdfActions::printIndividualPdfAction');

    expect($posEdit)->toBeInt()
        ->and($posFicha)->toBeInt()
        ->and($posEdit)->toBeLessThan($posFicha);

    expect($actionsContents)
        ->toContain("->label('Ficha Técnica de la Afiliación')")
        ->toContain("->icon('heroicon-o-printer')")
        ->toContain('business.affiliations.ficha.preview')
        ->toContain('business.affiliations.ficha.download')
        ->toContain('affiliation-ficha-preview-modal');

    expect($routesContents)
        ->toContain("->name('business.affiliations.ficha.preview')")
        ->toContain("->name('business.affiliations.ficha.download')");

    expect($controllerContents)
        ->toContain('AUDIT_BUSINESS_AFFILIATION_INDIVIDUAL_FICHA_VIEWED')
        ->toContain('AUDIT_BUSINESS_AFFILIATION_INDIVIDUAL_FICHA_DOWNLOADED')
        ->toContain('administration.affiliations.ficha.preview')
        ->toContain('administration.affiliations.ficha.download');
});

it('expone ficha técnica en acciones de afiliación corporativa de negocios', function (): void {
    $viewPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Pages/ViewAffiliationCorporate.php';
    $actionsPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Actions/AffiliationCorporateFichaPdfActions.php';
    $routesPath = dirname(__DIR__, 2).'/routes/web.php';
    $controllerPath = dirname(__DIR__, 2).'/app/Http/Controllers/AffiliationCorporateFichaPdfController.php';

    $viewContents = file_get_contents($viewPath);
    $actionsContents = file_get_contents($actionsPath);
    $routesContents = file_get_contents($routesPath);
    $controllerContents = file_get_contents($controllerPath);

    expect($viewContents)->toContain('AffiliationCorporateFichaPdfActions::printCorporatePdfAction');

    $posEdit = strpos($viewContents, "->label('Editar')");
    $posFicha = strpos($viewContents, 'AffiliationCorporateFichaPdfActions::printCorporatePdfAction');

    expect($posEdit)->toBeInt()
        ->and($posFicha)->toBeInt()
        ->and($posEdit)->toBeLessThan($posFicha);

    expect($actionsContents)
        ->toContain("->label('Ficha Técnica de la Afiliación')")
        ->toContain("->icon('heroicon-o-printer')")
        ->toContain('business.affiliation-corporates.ficha.preview')
        ->toContain('business.affiliation-corporates.ficha.download')
        ->toContain('affiliation-corporate-ficha-preview-modal');

    expect($routesContents)
        ->toContain("->name('business.affiliation-corporates.ficha.preview')")
        ->toContain("->name('business.affiliation-corporates.ficha.download')");

    expect($controllerContents)
        ->toContain('AUDIT_BUSINESS_AFFILIATION_CORPORATE_FICHA_VIEWED')
        ->toContain('AUDIT_BUSINESS_AFFILIATION_CORPORATE_FICHA_DOWNLOADED')
        ->toContain('administration.affiliation-corporates.ficha.preview')
        ->toContain('administration.affiliation-corporates.ficha.download');
});
