<?php

declare(strict_types=1);

it('define asignacion por colaborador o equipo en formulario de actividades', function (): void {
    $formPath = dirname(__DIR__, 2).'/app/Filament/Projects/Resources/ProjectManagement/Activities/Schemas/ActivityForm.php';
    $concernPath = dirname(__DIR__, 2).'/app/Filament/Projects/Resources/ProjectManagement/Activities/Concerns/InteractsWithActivityAssignmentForm.php';

    expect(file_exists($formPath))->toBeTrue();
    expect(file_exists($concernPath))->toBeTrue();

    $formContent = file_get_contents($formPath);
    $concernContent = file_get_contents($concernPath);

    expect($formContent)
        ->toContain("ToggleButtons::make('assignment_type')")
        ->toContain("Select::make('executor_group_id')")
        ->toContain("Select::make('assigned_collaborator_ids'")
        ->toContain("Action::make('create_team_from_activity')")
        ->toContain('->modalHeading(\'Nuevo equipo\')');

    expect($concernContent)
        ->toContain('normalizeActivityAssignmentFormData')
        ->toContain('hydrateActivityAssignmentFormData')
        ->toContain('assigned_collaborator_ids');
});
