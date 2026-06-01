<?php

declare(strict_types=1);

it('cases relation manager en ficha paciente usa estilo ios y badges de prioridad', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicinePatients/RelationManagers/TelemedicineCasesRelationManager.php';
    $c = file_get_contents($path);

    expect($c)
        ->toContain('telemedicine-case-table-ios')
        ->toContain('TelemedicinePriorityFilamentBadge::')
        ->toContain('->emptyStateHeading(')
        ->toContain('?from=patient')
        ->toContain('add_follow_up');
});

it('history relation manager en ficha paciente expone UX mejorada', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicinePatients/RelationManagers/TelemedicinePatientHistoryRelationManager.php';
    $c = file_get_contents($path);

    expect($c)
        ->toContain('telemedicine-case-table-ios')
        ->toContain('->emptyStateHeading(')
        ->toContain('register_history')
        ->toContain('ColumnGroup::make(\'Datos del paciente\'')
        ->toContain('Heroicon::OutlinedEye');
});
