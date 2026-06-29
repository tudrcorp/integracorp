<?php

declare(strict_types=1);

it('aplica el estilo ios a las tablas de antecedentes de la historia clínica', function (string $file): void {
    $path = dirname(__DIR__, 2)
        .'/app/Filament/Telemedicina/Resources/TelemedicineHistoryPatients/RelationManagers/'.$file;

    $c = file_get_contents($path);

    expect($c)
        ->toContain('telemedicine-case-table-ios')
        ->toContain("->defaultSort('created_at', 'desc')")
        ->toContain('emptyStateHeading')
        ->toContain('emptyStateDescription')
        ->toContain('recordActionsColumnLabel')
        ->toContain("->extraCellAttributes(['class' => 'py-3'])")
        ->toContain('FilamentIosButton::extraClassForFilamentColor')
        ->not->toContain('->modalButton(');
})->with([
    'FamilyHistoriesRelationManager.php',
    'GynecologicalHistoriesRelationManager.php',
    'NoPathologicalHistoriesRelationManager.php',
    'PathologicalHistoriesRelationManager.php',
    'SurgicalHistoriesRelationManager.php',
]);
