<?php

declare(strict_types=1);

use App\Support\ProjectManagement\ProjectManagementHelpGuide;

it('define guia de ayuda completa del modulo de proyectos', function (): void {
    $sections = ProjectManagementHelpGuide::sections();
    $toc = ProjectManagementHelpGuide::toc();

    expect($sections)->not->toBeEmpty();
    expect($toc)->toHaveCount(count($sections));

    $ids = collect($sections)->pluck('id')->all();

    expect($ids)
        ->toContain('inicio')
        ->toContain('menu')
        ->toContain('paso-a-paso')
        ->toContain('ejemplo-marketing')
        ->toContain('ejemplo-sistemas')
        ->toContain('kanban')
        ->toContain('metricas')
        ->toContain('faq');

    $encoded = mb_strtolower(json_encode($sections, JSON_UNESCAPED_UNICODE) ?: '');

    expect($encoded)
        ->toContain('plantillas')
        ->toContain('fuerza de venta')
        ->toContain('testigos')
        ->toContain('story points')
        ->toContain('burndown')
        ->toContain('kanban')
        ->toContain('backlog');
});

it('registra pagina ayuda con liquid glass y permisos', function (): void {
    $base = dirname(__DIR__, 2);

    expect(file_exists($base.'/app/Filament/Projects/Pages/Help.php'))->toBeTrue();
    expect(file_exists($base.'/resources/views/filament/projects/pages/help.blade.php'))->toBeTrue();
    expect(file_exists($base.'/app/Support/ProjectManagement/ProjectManagementHelpGuide.php'))->toBeTrue();

    expect(file_get_contents($base.'/app/Filament/Projects/Pages/Help.php'))
        ->toContain('ProjectManagementHelpGuide')
        ->toContain("protected static ?string \$slug = 'ayuda';")
        ->toContain('AuthorizesDepartmentNavigation');

    expect(file_get_contents($base.'/resources/views/filament/projects/pages/help.blade.php'))
        ->toContain('pm-help')
        ->toContain('pm-help__glass')
        ->toContain('pm-help__brand')
        ->toContain('pm-help__progress')
        ->toContain('pm-help__shortcut')
        ->toContain('backdrop-filter')
        ->toContain('Outfit')
        ->toContain('wire:model.live.debounce.250ms="search"')
        ->toContain('setActive(\'ejemplo-marketing\')')
        ->toContain('setActive(\'ejemplo-sistemas\')');

    expect(file_get_contents($base.'/app/Support/Filament/DepartmentNavigationPermissionRegistry.php'))
        ->toContain('Help::class => [\'ayuda-proyectos\']');

    expect(file_get_contents($base.'/app/Support/Filament/UserFormPermissionOptions.php'))
        ->toContain("'help' => ['ayuda-proyectos']");

    expect(file_get_contents($base.'/app/Providers/Filament/ProjectsPanelProvider.php'))
        ->toContain("->label('AYUDA')");
});
