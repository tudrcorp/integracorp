<?php

declare(strict_types=1);

use App\Filament\Metrics\Clusters\Negocios\CorretajeCluster;
use App\Filament\Metrics\Clusters\Negocios\ViajesCluster;
use App\Filament\Metrics\Pages\Administracion;
use App\Filament\Metrics\Pages\Afiliaciones;
use App\Filament\Metrics\Pages\Cotizaciones;
use App\Filament\Metrics\Pages\Negocios\Corretaje\CorretajeAgencies;
use App\Filament\Metrics\Pages\Negocios\Corretaje\CorretajeAgents;
use App\Filament\Metrics\Pages\Negocios\Viajes\ViajesAgencies;
use App\Filament\Metrics\Pages\Negocios\Viajes\ViajesAgents;
use App\Filament\Metrics\Pages\Operaciones;
use App\Filament\Metrics\Pages\Proveedores;
use App\Filament\Metrics\Pages\Proyectos;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Filament\MetricsPanelNavigationGroups;

it('define los grupos del menu lateral de Metricas/KPI', function (): void {
    expect(MetricsPanelNavigationGroups::labels())->toBe([
        'NEGOCIOS',
        'COTIZACIONES',
        'AFILIACIONES',
        'ADMINISTRACION',
        'PROVEEDORES',
        'OPERACIONES',
        'PROYECTOS',
    ]);
});

it('registra clusters y paginas del menu en el panel metrics', function (): void {
    $provider = file_get_contents(dirname(__DIR__, 2).'/app/Providers/Filament/MetricsPanelProvider.php');

    expect($provider)
        ->toContain('MetricsPanelNavigationGroups::definitions()')
        ->toContain("discoverClusters(in: app_path('Filament/Metrics/Clusters')")
        ->toContain("discoverPages(in: app_path('Filament/Metrics/Pages')");
});

it('estructura Negocios con Corretaje y Viajes anidados', function (): void {
    expect(CorretajeCluster::getNavigationGroup())->toBe('NEGOCIOS')
        ->and(CorretajeCluster::getNavigationLabel())->toBe('Corretaje')
        ->and(ViajesCluster::getNavigationGroup())->toBe('NEGOCIOS')
        ->and(ViajesCluster::getNavigationLabel())->toBe('Viajes')
        ->and(CorretajeAgents::getCluster())->toBe(CorretajeCluster::class)
        ->and(CorretajeAgencies::getCluster())->toBe(CorretajeCluster::class)
        ->and(ViajesAgencies::getCluster())->toBe(ViajesCluster::class)
        ->and(ViajesAgents::getCluster())->toBe(ViajesCluster::class)
        ->and(CorretajeAgents::getNavigationLabel())->toBe('Agentes')
        ->and(CorretajeAgencies::getNavigationLabel())->toBe('Agencias')
        ->and(ViajesAgencies::getNavigationLabel())->toBe('Agencias')
        ->and(ViajesAgents::getNavigationLabel())->toBe('Agentes');
});

it('muestra la subnavegacion de Corretaje y Viajes como tabs superiores', function (): void {
    expect(CorretajeCluster::getSubNavigationPosition())->toBe(\Filament\Pages\Enums\SubNavigationPosition::Top)
        ->and(ViajesCluster::getSubNavigationPosition())->toBe(\Filament\Pages\Enums\SubNavigationPosition::Top);

    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');

    expect($css)
        ->toContain('.fi-panel-metrics .fi-page-sub-navigation-tabs')
        ->toContain('fi-metrics-subnav-tabs');
});

it('expone paginas top-level para el resto de modulos', function (): void {
    expect(Cotizaciones::getNavigationGroup())->toBe('COTIZACIONES')
        ->and(Afiliaciones::getNavigationGroup())->toBe('AFILIACIONES')
        ->and(Administracion::getNavigationGroup())->toBe('ADMINISTRACION')
        ->and(Proveedores::getNavigationGroup())->toBe('PROVEEDORES')
        ->and(Operaciones::getNavigationGroup())->toBe('OPERACIONES')
        ->and(Proyectos::getNavigationGroup())->toBe('PROYECTOS');
});

it('registra permisos de navegacion Metricas en el registry', function (): void {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(CorretajeAgents::class))
        ->toBe(['metricas-corretaje-agentes'])
        ->and(DepartmentNavigationPermissionRegistry::slugsFor(Cotizaciones::class))
        ->toBe(['metricas-cotizaciones'])
        ->and(DepartmentNavigationPermissionRegistry::moduleFor(CorretajeAgents::class))
        ->toBe('METRICAS')
        ->and(DepartmentNavigationPermissionRegistry::moduleFor(Proyectos::class))
        ->toBe('METRICAS');
});

it('usa shell Liquid Glass compartido en las paginas de Metricas', function (): void {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/pages/module-shell.blade.php');
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');
    $trait = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Concerns/HasMetricsLiquidGlassPage.php');

    expect($trait)->toContain("return 'filament.metrics.pages.module-shell'")
        ->and($view)->toContain('fi-metrics-module')
        ->and($view)->not->toContain('fi-metrics-liquid-page__glass')
        ->and($view)->not->toContain('fi-metrics-liquid-page__backdrop')
        ->and($view)->toContain('fi-metrics-liquid-kpi')
        ->and($css)->toContain('.fi-panel-metrics')
        ->and($css)->toContain('saturate(var(--metrics-glass-sat))')
        ->and($css)->toContain('.fi-metrics-liquid-kpi');
});
