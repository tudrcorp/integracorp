<?php

declare(strict_types=1);

it('oculta relation managers de observaciones y referencias médicas en recurso telemedicina', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineCases/TelemedicineCaseResource.php');

    expect($contents)
        ->toContain('ConsultationsRelationManager::class')
        ->not->toContain('ObservationsRelationManager::class')
        ->not->toContain('TelemedicineDocumentsRelationManager::class');
});
