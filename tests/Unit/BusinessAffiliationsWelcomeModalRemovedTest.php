<?php

declare(strict_types=1);

it('no muestra modal de bienvenida en afiliaciones individuales de business', function (): void {
    $listAffiliationsPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Pages/ListAffiliations.php';
    $welcomeModalViewPath = dirname(__DIR__, 2).'/resources/views/filament/business/affiliations/welcome-modal.blade.php';

    expect(file_exists($listAffiliationsPath))->toBeTrue()
        ->and(file_exists($welcomeModalViewPath))->toBeFalse();

    $listAffiliations = file_get_contents($listAffiliationsPath);

    expect($listAffiliations)
        ->not->toContain('open-welcome-modal')
        ->not->toContain("Action::make('bienvenida')")
        ->not->toContain('filament.business.affiliations.welcome-modal');
});
