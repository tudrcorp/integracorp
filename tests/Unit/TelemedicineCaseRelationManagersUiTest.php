<?php

declare(strict_types=1);

it('observations relation manager define formulario y tabla coherente', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineCases/RelationManagers/ObservationsRelationManager.php';
    $c = file_get_contents($path);

    expect($c)
        ->toContain('public function form(')
        ->toContain('Textarea::make(\'description\')')
        ->toContain('mutateFormDataUsing')
        ->toContain('->defaultSort(\'created_at\', \'desc\')')
        ->toContain('emptyStateHeading');
});

it('consultations relation manager formatea listas de cobertura y estados', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineCases/RelationManagers/ConsultationsRelationManager.php';
    $c = file_get_contents($path);

    expect($c)
        ->toContain('formatCoverageList')
        ->toContain('coverageListIsFilled')
        ->toContain('consultationCoverageBadgesHtml')
        ->toContain('consultationCoverageCatalogBadgesHtml')
        ->toContain("'other_labs'")
        ->toContain("'other_studies'")
        ->toContain("'other_specialist'")
        ->toContain('TelemedicineCoverageCatalog::')
        ->toContain('coverageStatusBadgeHtml')
        ->toContain('svgIconShieldCheck')
        ->toContain('ColumnGroup::make(\'Cobertura\', [')
        ->toContain('->striped()');
});

it('documents relation manager usa vista previa condicional y panel', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineCases/RelationManagers/TelemedicineDocumentsRelationManager.php';
    $c = file_get_contents($path);

    expect($c)
        ->toContain('isPreviewableImage')
        ->toContain('Panel::make')
        ->toContain('emptyStateHeading');
});
