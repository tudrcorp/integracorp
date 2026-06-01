<?php

declare(strict_types=1);

it('observations relation manager en telemedicina define formulario y tabla coherente', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineCases/RelationManagers/ObservationsRelationManager.php';
    $c = file_get_contents($path);

    expect($c)
        ->toContain('public function form(')
        ->toContain('Textarea::make(\'description\')')
        ->toContain('mutateFormDataUsing')
        ->toContain('->defaultSort(\'created_at\', \'desc\')')
        ->toContain('emptyStateHeading')
        ->toContain('telemedicine-case-table-ios');
});

it('documents relation manager en telemedicina usa vista previa condicional y panel', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineCases/RelationManagers/TelemedicineDocumentsRelationManager.php';
    $c = file_get_contents($path);

    expect($c)
        ->toContain('isPreviewableImage')
        ->toContain('Panel::make')
        ->toContain('emptyStateHeading')
        ->toContain("->color('success')")
        ->not->toContain("'verde'");
});

it('consultations relation manager en telemedicina aplica estilo ios en tabla', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineCases/RelationManagers/ConsultationsRelationManager.php';
    $c = file_get_contents($path);

    expect($c)
        ->toContain('telemedicine-case-table-ios')
        ->toContain('recordActionsColumnLabel')
        ->toContain('isToggledHiddenByDefault: true');
});
