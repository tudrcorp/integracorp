<?php

declare(strict_types=1);

it('aplica mejoras de ux en tablas del modulo de proyectos', function (): void {
    $tablePaths = [
        'Departments/Tables/DepartmentsTable.php',
        'Groups/Tables/GroupsTable.php',
        'Projects/Tables/ProjectsTable.php',
        'Subprojects/Tables/SubprojectsTable.php',
        'Activities/Tables/ActivitiesTable.php',
    ];

    $basePath = dirname(__DIR__, 2).'/app/Filament/Projects/Resources/ProjectManagement/';

    foreach ($tablePaths as $relativePath) {
        $content = file_get_contents($basePath.$relativePath);

        expect($content)
            ->toContain("->defaultSort('created_at', 'desc')")
            ->toContain('->emptyStateHeading(')
            ->toContain('->emptyStateDescription(')
            ->toContain('->filters([')
            ->toContain("->label('Ver')")
            ->toContain("->label('Editar')")
            ->toContain('->striped()');

        if (str_contains($relativePath, 'Subprojects/')
            || str_contains($relativePath, 'Activities/')
            || str_contains($relativePath, 'Groups/')) {
            expect($content)
                ->toContain('->recordUrl(')
                ->toContain('->paginated([10, 25, 50])')
                ->toContain('ViewColumn::make(');
        }
    }
});
