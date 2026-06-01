<?php

declare(strict_types=1);

use App\Models\ProjectManagement\Project;
use App\Support\Filament\ProjectManagement\ProjectManagementProjectInfolistDisplay;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class);

it('resuelve payload resaltado de fechas del proyecto', function (): void {
    $project = new Project([
        'name' => 'Portal clientes',
        'color' => '#2563eb',
        'start_date' => Carbon::parse('2026-01-01'),
        'end_date' => Carbon::parse('2026-03-01'),
    ]);

    $payload = ProjectManagementProjectInfolistDisplay::datesPayload($project);

    expect($payload)
        ->project_name->toBe('Portal clientes')
        ->project_color->toBe('#2563eb')
        ->has_start->toBeTrue()
        ->has_end->toBeTrue()
        ->start_label->not->toBe('—')
        ->end_label->not->toBe('—');
});

it('consolida fechas y descripcion resaltadas en el tab general del infolist de proyectos', function (): void {
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Projects/Resources/ProjectManagement/Projects/Schemas/ProjectInfolist.php');

    expect($infolist)
        ->toContain("Tab::make('General')")
        ->toContain('dates_highlight')
        ->toContain('description_highlight')
        ->toContain('project-dates-highlight')
        ->toContain('project-description-highlight')
        ->not->toContain("Tab::make('Fechas')")
        ->not->toContain("Tab::make('Descripción')");
});

it('incluye vistas resaltadas de fechas y descripcion del proyecto', function (): void {
    $dates = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/projects/infolists/project-dates-highlight.blade.php');
    $description = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/projects/infolists/project-description-highlight.blade.php');

    expect($dates)
        ->toContain('Fecha de inicio')
        ->toContain('Fecha de fin')
        ->toContain('project-dates-highlight__value');

    expect($description)
        ->toContain('Descripción del proyecto')
        ->toContain('Detalle resaltado')
        ->toContain('project-description-highlight__text');
});

it('expone tab de diagrama de proyecto con payload de flujo optimizado', function (): void {
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Projects/Resources/ProjectManagement/Projects/Schemas/ProjectInfolist.php');
    $display = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/ProjectManagement/ProjectManagementProjectInfolistDisplay.php');
    $diagram = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/projects/infolists/project-flow-diagram.blade.php');
    $viewProject = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Projects/Resources/ProjectManagement/Projects/Pages/ViewProject.php');

    expect($infolist)
        ->toContain("Tab::make('Diagrama de Proyecto')")
        ->toContain('flow_diagram')
        ->toContain('project-flow-diagram')
        ->and($display)
        ->toContain('flowDiagramPayload')
        ->toContain('activities_done_count')
        ->and($diagram)
        ->toContain('project-flow-diagram__canvas')
        ->toContain('statusFilter')
        ->toContain('Diagrama de flujo')
        ->and($viewProject)
        ->toContain('activities_open_count');
});

it('resuelve payload del diagrama de flujo con subproyectos mapeados', function (): void {
    Filament::setCurrentPanel('projects');

    $project = Project::make([
        'name' => 'Plataforma interna',
        'color' => '#0ea5e9',
        'icon' => 'heroicon-o-folder',
        'status' => 'active',
    ]);
    $project->id = 99;
    $project->exists = true;

    $subproject = \App\Models\ProjectManagement\Subproject::make([
        'project_id' => 99,
        'name' => 'Fase de descubrimiento',
        'description' => 'Levantamiento de requerimientos.',
        'status' => 'active',
    ]);
    $subproject->id = 7;
    $subproject->exists = true;
    $subproject->setAttribute('activities_count', 4);
    $subproject->setAttribute('activities_done_count', 2);
    $subproject->setAttribute('activities_open_count', 2);

    $project->setRelation('subprojects', collect([$subproject]));

    $payload = ProjectManagementProjectInfolistDisplay::flowDiagramPayload($project);

    expect($payload)
        ->has_subprojects->toBeTrue()
        ->and($payload['stats']['subprojects_total'])->toBe(1)
        ->and($payload['stats']['activities_total'])->toBe(4)
        ->and($payload['stats']['overall_percent'])->toBe(50)
        ->and($payload['subprojects'][0]['name'])->toBe('Fase de descubrimiento')
        ->and($payload['subprojects'][0]['view_url'])->toContain('/project-management/subprojects/7');
});
