<?php

declare(strict_types=1);

it('registra resources del panel projects para gestion de proyectos', function (): void {
    $resourcePaths = [
        dirname(__DIR__, 2).'/app/Filament/Projects/Resources/ProjectManagement/Departments/DepartmentResource.php',
        dirname(__DIR__, 2).'/app/Filament/Projects/Resources/ProjectManagement/Groups/GroupResource.php',
        dirname(__DIR__, 2).'/app/Filament/Projects/Resources/ProjectManagement/Projects/ProjectResource.php',
        dirname(__DIR__, 2).'/app/Filament/Projects/Resources/ProjectManagement/Subprojects/SubprojectResource.php',
        dirname(__DIR__, 2).'/app/Filament/Projects/Resources/ProjectManagement/Activities/ActivityResource.php',
    ];

    foreach ($resourcePaths as $path) {
        expect(file_exists($path))->toBeTrue();

        $content = file_get_contents($path);

        expect($content)
            ->toContain('GESTION DE PROYECTOS')
            ->toContain('public static function form(Schema $schema): Schema')
            ->toContain('public static function table(Table $table): Table')
            ->toContain('public static function getPages(): array');
    }

    expect(file_get_contents($resourcePaths[2]))
        ->toContain('use App\\Models\\ProjectManagement\\Project;')
        ->not->toContain('ProjectManagement\\ProjectManagement');
});
