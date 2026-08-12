<?php

declare(strict_types=1);

use App\Filament\Business\Resources\AffiliationCorporates\Widgets\StatsOverview;
use App\Filament\Business\Resources\AffiliationCorporates\Widgets\StatsOverviewPlan;
use Filament\Widgets\StatsOverviewWidget;

it('registra los widgets de stats corporativos activos', function (): void {
    expect(class_exists(StatsOverview::class))->toBeTrue()
        ->and(is_subclass_of(StatsOverview::class, StatsOverviewWidget::class))->toBeTrue()
        ->and(class_exists(StatsOverviewPlan::class))->toBeTrue()
        ->and(is_subclass_of(StatsOverviewPlan::class, StatsOverviewWidget::class))->toBeTrue();
});

it('muestra grupos activos junto con agencias y agentes vinculados', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Widgets/StatsOverview.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("->where('status', 'ACTIVA')")
        ->toContain('Grupos Activos')
        ->toContain('Agencias vinculadas a grupos activos')
        ->toContain('Agentes vinculados a grupos activos')
        ->toContain('COUNT(DISTINCT code_agency) as agencies_count')
        ->toContain('COUNT(DISTINCT agent_id) as agents_count')
        ->toContain('AffiliationCorporate::query()')
        ->toContain('is_accountManagers')
        ->toContain('->toBase()')
        ->not->toContain('getPageTableQuery')
        ->not->toContain('Total histórico / Acumulado');
});

it('valida afiliados activos por plan y su totalidad en grupos activos', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Widgets/StatsOverviewPlan.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain('AffiliateCorporate::query()')
        ->toContain("->where('status', 'ACTIVO')")
        ->toContain("->where('status', 'ACTIVA')")
        ->toContain('AFILIADOS ACTIVOS POR PLAN')
        ->toContain('PLAN ESCOLAR AP 1K')
        ->toContain('PLAN ESCOLAR AP 3K')
        ->toContain('TOTAL ACTIVOS')
        ->toContain('array_sum($planStatsTotal)')
        ->toContain('16 =>')
        ->toContain('17 =>')
        ->not->toContain('AfilliationCorporatePlan::query()');
});
