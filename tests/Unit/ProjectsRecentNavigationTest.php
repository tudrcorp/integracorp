<?php

declare(strict_types=1);

it('configura grupo de navegacion para ultimos proyectos', function (): void {
    $providerPath = dirname(__DIR__, 2).'/app/Providers/Filament/ProjectsPanelProvider.php';
    $navigationSupportPath = dirname(__DIR__, 2).'/app/Support/Filament/ProjectManagement/RecentProjectsNavigation.php';

    expect(file_exists($providerPath))->toBeTrue();
    expect(file_exists($navigationSupportPath))->toBeTrue();

    $providerContent = file_get_contents($providerPath);
    $navigationSupportContent = file_get_contents($navigationSupportPath);

    expect($providerContent)
        ->toContain("->label('PROYECTOS RECIENTES')")
        ->toContain('->navigationItems(RecentProjectsNavigation::items())');

    expect($navigationSupportContent)
        ->toContain("->group('PROYECTOS RECIENTES')")
        ->toContain('->limit(5)')
        ->toContain('Str::limit($project->name, 34)')
        ->toContain("ProjectResource::getUrl('edit'");
});
