<?php

declare(strict_types=1);

use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    Mail::fake();
    Queue::fake();
});

afterEach(function (): void {
    DB::rollBack();
});

it('registra el permiso de recálculo individual en el catálogo de acciones', function (): void {
    $all = App\Support\Filament\BusinessFilamentActionPermissionRegistry::all();

    expect($all)->toHaveKey(BusinessFilamentActionPermissionRegistry::RECALCULATE_INDIVIDUAL_TOTALS);
});

it('expone la acción Recalcular totales en la tabla de afiliaciones individuales', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Tables/AffiliationsTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('RecalculateIndividualTotalsAction::make()')
        ->toContain('ActivePopulationIndividualTotals::applyToQuery');
});

it('los widgets de agregación limpian el SELECT de población activa', function (): void {
    $stats = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Widgets/StatsOverview.php');
    $suppliers = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Widgets/AffiliationSupplierChart.php');
    $helper = file_get_contents(dirname(__DIR__, 2).'/app/Support/Affiliations/ActivePopulationIndividualTotals.php');

    expect($helper)->toContain('function withoutLiveSelects')
        ->and($stats)->toContain('ActivePopulationIndividualTotals::withoutLiveSelects')
        ->and($suppliers)->toContain('ActivePopulationIndividualTotals::withoutLiveSelects');
});

it('la subconsulta de upgrades enlaza por affiliates.affiliation_id', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/Affiliations/ActivePopulationIndividualTotals.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('upgrade_affiliates.affiliation_id')
        ->not->toContain('affiliate_upgrades.affiliation_id');
});

it('sincroniza filas de plan con CorporatePlanRowSync al recalcular corporativo', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/AffiliationCorporates/ActivePopulationTotals.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('CorporatePlanRowSync::sync')
        ->toContain('plan_rows_amounts_differ');
});
