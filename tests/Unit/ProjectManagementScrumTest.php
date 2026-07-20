<?php

declare(strict_types=1);

use App\Enums\ProjectManagement\CeremonyType;
use App\Enums\ProjectManagement\EpicStatus;
use App\Enums\ProjectManagement\SprintStatus;
use App\Support\ProjectManagement\BacklogOrdering;
use App\Support\ProjectManagement\BurndownChartData;
use App\Support\ProjectManagement\SprintLifecycle;
use App\Support\ProjectManagement\SprintMetricsRecorder;
use App\Support\ProjectManagement\VelocityCalculator;

it('define enums scrum del modulo de proyectos', function (): void {
    expect(SprintStatus::options())
        ->toHaveKey('planned')
        ->toHaveKey('active')
        ->toHaveKey('completed');

    expect(EpicStatus::options())
        ->toHaveKey('open')
        ->toHaveKey('done');

    expect(CeremonyType::options())
        ->toHaveKey('planning')
        ->toHaveKey('daily')
        ->toHaveKey('review')
        ->toHaveKey('retro');
});

it('define modelos scrum y campos de actividad', function (): void {
    $base = dirname(__DIR__, 2).'/app/Models/ProjectManagement';

    expect(file_get_contents($base.'/Epic.php'))
        ->toContain('Epic extends Model')
        ->toContain('public function activities(): HasMany')
        ->toContain('EpicStatus::class');

    expect(file_get_contents($base.'/Sprint.php'))
        ->toContain('Sprint extends Model')
        ->toContain('public function ceremonies(): HasMany')
        ->toContain('public function dailyMetrics(): HasMany')
        ->toContain('SprintStatus::class');

    expect(file_get_contents($base.'/ProjectScrumRole.php'))
        ->toContain('product_owner_id')
        ->toContain('scrum_master_id');

    expect(file_get_contents($base.'/SprintCeremony.php'))
        ->toContain('CeremonyType::class')
        ->toContain('facilitator_id');

    expect(file_get_contents($base.'/SprintDailyMetric.php'))
        ->toContain('committed_points')
        ->toContain('remaining_points');

    expect(file_get_contents($base.'/Activity.php'))
        ->toContain("'epic_id'")
        ->toContain("'sprint_id'")
        ->toContain("'story_points'")
        ->toContain("'backlog_order'")
        ->toContain("'acceptance_criteria'")
        ->toContain("'completed_at'")
        ->toContain('TracksActivityScrumMetrics')
        ->toContain('public function epic(): BelongsTo')
        ->toContain('public function sprint(): BelongsTo');

    expect(file_get_contents($base.'/Project.php'))
        ->toContain('public function epics(): HasMany')
        ->toContain('public function sprints(): HasMany')
        ->toContain('public function scrumRoles(): HasOne')
        ->toContain('public function activeSprint(): HasOne');
});

it('define servicios de dominio scrum', function (): void {
    expect(class_exists(SprintLifecycle::class))->toBeTrue();
    expect(class_exists(BacklogOrdering::class))->toBeTrue();
    expect(class_exists(SprintMetricsRecorder::class))->toBeTrue();
    expect(class_exists(BurndownChartData::class))->toBeTrue();
    expect(class_exists(VelocityCalculator::class))->toBeTrue();

    $lifecycle = file_get_contents(dirname(__DIR__, 2).'/app/Support/ProjectManagement/SprintLifecycle.php');
    expect($lifecycle)
        ->toContain('function activate')
        ->toContain('function complete')
        ->toContain("where('status', '!=', 'done')")
        ->toContain("'sprint_id' => null");

    $ordering = file_get_contents(dirname(__DIR__, 2).'/app/Support/ProjectManagement/BacklogOrdering.php');
    expect($ordering)
        ->toContain('function reorder')
        ->toContain('function moveUp')
        ->toContain('function moveDown');

    $burndown = file_get_contents(dirname(__DIR__, 2).'/app/Support/ProjectManagement/BurndownChartData.php');
    expect($burndown)
        ->toContain("'labels'")
        ->toContain("'ideal'")
        ->toContain("'remaining'");
});

it('registra resources y pagina backlog scrum en filament', function (): void {
    $base = dirname(__DIR__, 2);

    expect(file_exists($base.'/app/Filament/Projects/Resources/ProjectManagement/Epics/EpicResource.php'))->toBeTrue();
    expect(file_exists($base.'/app/Filament/Projects/Resources/ProjectManagement/Sprints/SprintResource.php'))->toBeTrue();
    expect(file_exists($base.'/app/Filament/Projects/Pages/Backlog.php'))->toBeTrue();
    expect(file_exists($base.'/resources/views/filament/projects/pages/backlog.blade.php'))->toBeTrue();
    expect(file_exists($base.'/resources/views/filament/projects/infolists/sprint-burndown.blade.php'))->toBeTrue();

    expect(file_get_contents($base.'/app/Filament/Projects/Resources/ProjectManagement/Epics/EpicResource.php'))
        ->toContain("protected static ?string \$navigationLabel = 'Épicas';")
        ->toContain('AuthorizesDepartmentNavigation')
        ->toContain('protected static ?int $navigationSort = 2;');

    expect(file_get_contents($base.'/app/Filament/Projects/Resources/ProjectManagement/Sprints/SprintResource.php'))
        ->toContain("protected static ?string \$navigationLabel = 'Sprints';")
        ->toContain('CeremoniesRelationManager')
        ->toContain('protected static ?int $navigationSort = 4;');

    expect(file_get_contents($base.'/app/Filament/Projects/Pages/Backlog.php'))
        ->toContain("protected static ?string \$navigationLabel = 'Backlog';")
        ->toContain('BacklogOrdering')
        ->toContain('whereNull(\'sprint_id\')')
        ->toContain("'executor_type' => null")
        ->toContain("'executor_id' => null")
        ->toContain('protected static ?int $navigationSort = 5;');

    expect(file_get_contents($base.'/app/Filament/Projects/Resources/ProjectManagement/Sprints/Pages/ViewSprint.php'))
        ->toContain('activateSprint')
        ->toContain('completeSprint')
        ->toContain('SprintLifecycle');
});

it('extiende formularios de actividad y proyecto con campos scrum', function (): void {
    $base = dirname(__DIR__, 2);

    expect(file_get_contents($base.'/app/Filament/Projects/Resources/ProjectManagement/Activities/Schemas/ActivityForm.php'))
        ->toContain("Select::make('epic_id')")
        ->toContain("Select::make('sprint_id')")
        ->toContain("TextInput::make('story_points')")
        ->toContain("Textarea::make('acceptance_criteria')");

    expect(file_get_contents($base.'/app/Filament/Projects/Resources/ProjectManagement/Projects/Schemas/ProjectForm.php'))
        ->toContain("Select::make('product_owner_id')")
        ->toContain("Select::make('scrum_master_id')");

    expect(file_get_contents($base.'/app/Filament/Projects/Resources/ProjectManagement/Projects/Pages/EditProject.php'))
        ->toContain('InteractsWithProjectScrumRoles')
        ->toContain('product_owner_id');
});

it('incluye filtro de sprint en kanban y consulta optimizada', function (): void {
    $base = dirname(__DIR__, 2);

    expect(file_get_contents($base.'/app/Filament/Projects/Pages/Kanban.php'))
        ->toContain("public string \$sprintFilter = 'active';")
        ->toContain('getSprintOptionsProperty')
        ->toContain('getSprintPointsSummaryProperty')
        ->toContain('updatedSprintFilter');

    expect(file_get_contents($base.'/app/Support/Filament/ProjectManagement/ProjectManagementKanbanActivitiesQuery.php'))
        ->toContain('string $sprintFilter = \'active\'')
        ->toContain("\$sprintFilter === 'backlog'")
        ->toContain("\$sprintFilter === 'active'");

    expect(file_get_contents($base.'/resources/views/filament/projects/pages/kanban.blade.php'))
        ->toContain('wire:model.live="sprintFilter"')
        ->toContain('Sprint activo')
        ->toContain('Product Backlog')
        ->toContain('sprintPointsSummary');
});

it('registra permisos scrum en registries de navegacion', function (): void {
    $registry = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/DepartmentNavigationPermissionRegistry.php');
    $options = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/UserFormPermissionOptions.php');

    expect($registry)
        ->toContain('EpicResource::class => [\'epicas\']')
        ->toContain('SprintResource::class => [\'sprints\']')
        ->toContain('Backlog::class => [\'backlog\']');

    expect($options)
        ->toContain("'epicresource' => ['epicas']")
        ->toContain("'sprintresource' => ['sprints']")
        ->toContain("'backlog' => ['backlog']");
});

it('incluye migracion scrum con tablas e indices', function (): void {
    $migration = file_get_contents(
        dirname(__DIR__, 2).'/database/migrations/2026_07_17_223449_create_project_management_scrum_tables.php',
    );

    expect($migration)
        ->toContain("Schema::create('epics'")
        ->toContain("Schema::create('sprints'")
        ->toContain("Schema::create('project_scrum_roles'")
        ->toContain("Schema::create('sprint_ceremonies'")
        ->toContain("Schema::create('sprint_daily_metrics'")
        ->toContain("foreignId('epic_id')")
        ->toContain("foreignId('sprint_id')")
        ->toContain('story_points')
        ->toContain('backlog_order')
        ->toContain('acceptance_criteria')
        ->toContain('completed_at')
        ->toContain('activities_project_sprint_status_index');
});
