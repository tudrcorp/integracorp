<?php

declare(strict_types=1);

it('aplica eager load y deferLoading en la tabla de afiliaciones individuales', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Tables/AffiliationsTable.php');

    expect($source)
        ->toContain('->deferLoading()')
        ->toContain('->modifyQueryUsing(fn (Builder $query): Builder => $query->with([')
        ->toContain("'individual_quote'")
        ->toContain("'accountManager'")
        ->toContain("'agency'")
        ->toContain("'agent'")
        ->toContain("'plan'")
        ->toContain("'coverage'")
        ->toContain("'businessUnit'")
        ->toContain("'businessLine'")
        ->toContain("'city'")
        ->toContain("'state'")
        ->toContain("'country'");
});

it('consolida las queries de widgets del listado de afiliaciones individuales', function (): void {
    $statsOverview = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Widgets/StatsOverview.php');
    $statsPlan = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Widgets/StatsOverviewPlan.php');
    $planChart = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Widgets/AffiliationPlanChart.php');
    $supplierChart = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Widgets/AffiliationSupplierChart.php');
    $estadoChart = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Widgets/TotalAfiliacionesPorEstado.php');

    expect($statsOverview)
        ->toContain("selectRaw('COUNT(*) as total_count')")
        ->toContain('transition-[transform,box-shadow,border-color]')
        ->not->toContain('transition-all');

    expect($statsPlan)
        ->toContain("->groupBy('plan_id')")
        ->toContain('transition-[transform,box-shadow,border-color]')
        ->not->toContain("Affiliate::where('plan_id', 1)")
        ->not->toContain("Affiliate::where('plan_id', 2)")
        ->not->toContain("Affiliate::where('plan_id', 3)");

    expect($planChart)
        ->toContain("->groupBy('plan_id')")
        ->not->toContain('$affiliations = $this->getPageTableQuery()');

    expect($supplierChart)
        ->toContain("SUM(CASE WHEN service_providers LIKE '%ATENMEDI%'")
        ->toContain("SUM(CASE WHEN service_providers LIKE '%ILS%'")
        ->toContain("SUM(CASE WHEN service_providers LIKE '%TDEC%'")
        ->not->toContain('ServiceProvider::all')
        ->not->toContain('array_push($supplierIds');

    expect($estadoChart)
        ->toContain('City::query()')
        ->toContain("->pluck('definition', 'id')")
        ->not->toContain("DB::table('cities')->where('id', \$stat->city_id_ti)");
});

it('cachea el badge de navegacion de afiliaciones individuales', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/AffiliationResource.php');

    expect($source)
        ->toContain('Cache::remember')
        ->toContain('business.affiliation_navigation_badge.')
        ->toContain('now()->addSeconds(60)');
});
