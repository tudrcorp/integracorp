<?php

declare(strict_types=1);

it('aplica eager load y deferLoading en la tabla de afiliaciones corporativas', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php');

    expect($source)
        ->toContain('->deferLoading()')
        ->toContain('->modifyQueryUsing(fn (Builder $query): Builder => $query->with([')
        ->toContain("'agency'")
        ->toContain("'agent'")
        ->toContain("'accountManager'")
        ->toContain("'city'")
        ->toContain("'state'")
        ->toContain("'country'");
});

it('consolida las queries de widgets del listado de afiliaciones corporativas', function (): void {
    $statsOverview = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Widgets/StatsOverview.php');
    $statsPlan = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Widgets/StatsOverviewPlan.php');
    $estadoChart = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Widgets/AffiliationCorporatePorEstadoChart.php');

    expect($statsOverview)
        ->toContain("selectRaw('COUNT(*) as total_count')")
        ->toContain("selectRaw('COUNT(DISTINCT code_agency) as agencies_count')")
        ->toContain("selectRaw('COUNT(DISTINCT agent_id) as agents_count')")
        ->toContain('Grupos Activos')
        ->toContain('transition-[transform,box-shadow,border-color]')
        ->not->toContain('transition-all')
        ->not->toContain('[&_*]:transition-all');

    expect($statsPlan)
        ->toContain('AffiliateCorporate::query()')
        ->toContain("->where('status', 'ACTIVO')")
        ->toContain("->groupBy('plan_id')")
        ->toContain('PLAN ESCOLAR AP 1K')
        ->toContain('PLAN ESCOLAR AP 3K')
        ->toContain('TOTAL ACTIVOS')
        ->toContain('transition-[transform,box-shadow,border-color]')
        ->not->toContain('transition-all')
        ->not->toContain('[&_*]:transition-all')
        ->not->toContain('AfilliationCorporatePlan::query()');

    expect($estadoChart)
        ->toContain('City::query()')
        ->toContain("->pluck('definition', 'id')")
        ->not->toContain("DB::table('cities')->where('id', \$stat->city_id)");
});

it('cachea el badge de navegacion de afiliaciones corporativas', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/AffiliationCorporateResource.php');

    expect($source)
        ->toContain('Cache::remember')
        ->toContain('business.affiliation_corporate_navigation_badge.')
        ->toContain('now()->addSeconds(60)');
});
