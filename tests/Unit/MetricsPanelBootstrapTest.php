<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Filament\InternalPanelDepartmentMap;
use App\Support\Filament\InternalPanelsQuickNavigation;
use App\Support\Filament\UserPermissionFormUi;
use App\Support\Filament\UserTableUi;
use Filament\Facades\Filament;
use Tests\TestCase;

uses(TestCase::class);

it('registra el panel metrics con marca Metricas/KPI', function (): void {
    $provider = file_get_contents(dirname(__DIR__, 2).'/app/Providers/Filament/MetricsPanelProvider.php');
    $providers = file_get_contents(dirname(__DIR__, 2).'/bootstrap/providers.php');
    $dashboard = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Pages/Dashboard.php');
    $hero = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/pages/dashboard-hero.blade.php');
    $theme = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');

    expect($providers)->toContain('MetricsPanelProvider::class')
        ->and($provider)->toContain("->id('metrics')")
        ->and($provider)->toContain("->path('metrics')")
        ->and($provider)->toContain("->brandName('Métricas/KPI')")
        ->and($provider)->toContain('App\\Filament\\Metrics\\Pages\\Dashboard')
        ->and($provider)->not->toContain('Filament\\Pages\\Dashboard')
        ->and($provider)->toContain("app_path('Filament/Metrics/Resources')")
        ->and($provider)->toContain("view('filament.panels.internal-quick-nav')")
        ->and($dashboard)->toContain('return null')
        ->and($dashboard)->toContain("View::make('filament.metrics.pages.dashboard-hero')")
        ->and($dashboard)->toContain("navigationLabel = 'Inicio'")
        ->and($hero)->toContain('fi-metrics-dashboard-hero')
        ->and($hero)->toContain('Mide con precisión.')
        ->and($hero)->toContain('Decide con claridad.')
        ->and($hero)->not->toContain('Escritorio')
        ->and($theme)->toContain('.fi-metrics-dashboard-hero')
        ->and($theme)->toContain('.fi-metrics-dashboard-hero__title-accent');
});

it('mapea el panel metrics al modulo METRICAS', function (): void {
    expect(InternalPanelDepartmentMap::moduleForPanel('metrics'))->toBe('METRICAS')
        ->and(InternalPanelDepartmentMap::moduleForClass('App\\Filament\\Metrics\\Resources\\DemoResource'))
        ->toBe('METRICAS');
});

it('expone etiquetas de Metricas/KPI en UI de modulos y permisos', function (): void {
    expect(UserPermissionFormUi::moduleDisplayLabel('METRICAS'))->toBe('Métricas/KPI')
        ->and(UserPermissionFormUi::moduleMenuSubtitle('METRICAS'))->toBe('Indicadores y KPI')
        ->and(UserTableUi::moduleShortLabel('METRICAS'))->toBe('Métricas/KPI');
});

it('solo permite acceso al panel metrics a SUPERADMIN con modulo METRICAS', function (): void {
    $panel = Filament::getPanel('metrics');

    $superadminWithMetrics = User::factory()->make([
        'email' => 'superadmin-metrics@tudrencasa.com',
        'departament' => ['SUPERADMIN', 'METRICAS'],
        'status' => 'ACTIVO',
    ]);

    $superadminWithoutMetrics = User::factory()->make([
        'email' => 'superadmin-only@tudrencasa.com',
        'departament' => ['SUPERADMIN'],
        'status' => 'ACTIVO',
    ]);

    $metricsWithoutSuperadmin = User::factory()->make([
        'email' => 'metrics-user@tudrencasa.com',
        'departament' => ['METRICAS'],
        'status' => 'ACTIVO',
    ]);

    expect($superadminWithMetrics->canAccessPanel($panel))->toBeTrue()
        ->and($superadminWithoutMetrics->canAccessPanel($panel))->toBeFalse()
        ->and($metricsWithoutSuperadmin->canAccessPanel($panel))->toBeFalse();
});

it('muestra Metricas/KPI en el stepper solo para SUPERADMIN', function (): void {
    $superadmin = User::factory()->make([
        'email' => 'superadmin-metrics-nav@tudrencasa.com',
        'departament' => ['SUPERADMIN', 'METRICAS'],
        'status' => 'ACTIVO',
        'is_admin' => true,
    ]);

    $this->actingAs($superadmin);

    $panelIds = collect(InternalPanelsQuickNavigation::navigationItems('business'))
        ->where('kind', 'panel')
        ->pluck('panel_id')
        ->all();

    expect($panelIds)->toContain('metrics');

    $opsUser = User::factory()->make([
        'email' => 'ops-metrics-nav@tudrencasa.com',
        'departament' => ['OPERACIONES', 'METRICAS'],
        'status' => 'ACTIVO',
    ]);

    $this->actingAs($opsUser);

    $opsPanelIds = collect(InternalPanelsQuickNavigation::navigationItems('operations'))
        ->where('kind', 'panel')
        ->pluck('panel_id')
        ->all();

    expect($opsPanelIds)->not->toContain('metrics');
});

it('restringe Metricas/KPI en quick navigation a SUPERADMIN', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/InternalPanelsQuickNavigation.php');

    expect($source)
        ->toContain("\$panelId === 'metrics' && ! \$isSuperAdmin")
        ->toContain("'id' => 'metrics'")
        ->toContain("'department' => 'METRICAS'");
});

it('incluye el rol METRICAS en la migracion de modulo', function (): void {
    $migration = file_get_contents(
        dirname(__DIR__, 2).'/database/migrations/2026_07_25_000204_create_metricas_rol_module.php'
    );

    expect($migration)
        ->toContain("'METRICAS'")
        ->toContain('Métricas y KPI')
        ->toContain("->where('name', 'METRICAS')");
});
