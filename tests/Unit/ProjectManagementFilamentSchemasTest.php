<?php

declare(strict_types=1);

use App\Support\Filament\ProjectManagement\ProjectManagementFilamentSchemas;

it('expone constantes de estilo ios alineadas al modulo de negocios', function (): void {
    expect(ProjectManagementFilamentSchemas::TABS_CONTAINER)
        ->toContain('rounded-[1.75rem]')
        ->toContain('bg-gradient-to-br');

    expect(ProjectManagementFilamentSchemas::IOS_SECTION_CLASS)
        ->toContain('rounded-[1.5rem]')
        ->toContain('bg-gradient-to-b');

    expect(ProjectManagementFilamentSchemas::IOS_INNER_CLASS)
        ->toContain('rounded-[1.25rem]')
        ->toContain('shadow-inner');
});

it('usa tabs con estilos de negocios en formularios e infolists de gestion de proyectos', function (): void {
    $schemaPaths = [
        'Departments/Schemas/DepartmentForm.php',
        'Departments/Schemas/DepartmentInfolist.php',
        'Groups/Schemas/GroupForm.php',
        'Groups/Schemas/GroupInfolist.php',
        'Projects/Schemas/ProjectForm.php',
        'Projects/Schemas/ProjectInfolist.php',
        'Subprojects/Schemas/SubprojectForm.php',
        'Subprojects/Schemas/SubprojectInfolist.php',
        'Activities/Schemas/ActivityForm.php',
        'Activities/Schemas/ActivityInfolist.php',
    ];

    $base = dirname(__DIR__, 2).'/app/Filament/Projects/Resources/ProjectManagement/';

    foreach ($schemaPaths as $relativePath) {
        $content = file_get_contents($base.$relativePath);

        expect($content)
            ->toContain('ProjectManagementFilamentSchemas::tabbed')
            ->toContain('ProjectManagementFilamentSchemas::section')
            ->toContain('ProjectManagementFilamentSchemas::innerGrid');
    }

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/ProjectManagement/ProjectManagementFilamentSchemas.php'))
        ->toContain('->persistTab()')
        ->toContain('->persistTabInQueryString(\'tab\')')
        ->toContain('TABS_CONTAINER');
});
