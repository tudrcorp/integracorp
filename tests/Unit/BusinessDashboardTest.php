<?php

declare(strict_types=1);

use App\Filament\Business\Pages\Dashboard;
use App\Filament\Business\Widgets\AffiliationIndividualChart;
use App\Filament\Business\Widgets\Dashboard\BusinessKpiTiles;
use App\Filament\Business\Widgets\Dashboard\BusinessNewVsRenewalTiles;
use App\Filament\Business\Widgets\Dashboard\BusinessTeamRanking;
use App\Filament\Business\Widgets\Dashboard\BusinessVenezuelaMap;
use App\Filament\Business\Widgets\IndividualQuoteChart;
use App\Filament\Business\Widgets\SalesForAgency;
use App\Filament\Business\Widgets\StatsOverview;
use App\Filament\Business\Widgets\StatsOverviewSaleBusiness;
use App\Filament\Business\Widgets\StatsOverviewSaleUsdVesBusiness;
use App\Filament\Business\Widgets\TwoStatsOverview;
use App\Filament\Metrics\Widgets\Corporate\IncomeTrendChart;
use App\Filament\Metrics\Widgets\Corporate\SalesByAgentRanking;
use App\Models\User;
use App\Support\Business\Dashboard\BusinessDashboardMetrics;
use App\Support\Business\Dashboard\BusinessNewVsRenewalMetrics;
use App\Support\Metrics\Corporate\CorporateFinanceMetrics;
use App\Support\Metrics\Corporate\CorporateMetrics;
use App\Support\Metrics\Corporate\CorporatePortfolioMetrics;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod as Period;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Lee la conexión de la aplicación y escribe solo dentro de una transacción
 * revertida, en 2031 para no cruzarse con datos reales.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    CorporateMetrics::flush();
    Filament::setCurrentPanel('business');
});

afterEach(function (): void {
    DB::rollBack();
    CorporateMetrics::flush();
});

function bdFilters(): array
{
    return ['period' => Period::CUSTOM, 'from' => '2031-03-01', 'to' => '2031-03-31', 'compare' => true];
}

function bdPeriod(): Period
{
    return Period::fromFilters(bdFilters());
}

function bdActingAs(array $attributes = []): User
{
    $user = User::factory()->make([
        'id' => 999_999_081,
        'name' => 'QA NEGOCIOS',
        'email' => 'qa.negocios@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['NEGOCIOS'],
        ...$attributes,
    ]);
    test()->actingAs($user);

    return $user;
}

function bdQuote(string $table, string $createdBy): void
{
    $columns = array_flip(DB::getSchemaBuilder()->getColumnListing($table));
    $row = array_intersect_key([
        'created_by' => $createdBy, 'status' => 'PRE-APROBADA', 'created_at' => '2031-03-10 10:00:00', 'updated_at' => '2031-03-10 10:00:00',
        'code' => 'QA-'.uniqid(), 'full_name' => 'QA', 'email' => 'qa@example.com', 'phone' => '0', 'state_id' => null,
    ], $columns);
    DB::table($table)->insert($row);
}

/**
 * Afiliación mínima: rellena las columnas obligatorias sin valor por defecto.
 *
 * @param  array<string, mixed>  $attributes
 */
function bdInsertAffiliation(array $attributes): void
{
    $required = DB::table('information_schema.COLUMNS')
        ->whereRaw('TABLE_SCHEMA = DATABASE()')
        ->where('TABLE_NAME', 'affiliations')
        ->where('IS_NULLABLE', 'NO')
        ->whereNull('COLUMN_DEFAULT')
        ->where('EXTRA', 'not like', '%auto_increment%')
        ->get(['COLUMN_NAME as name', 'DATA_TYPE as type']);
    $row = [];

    foreach ($required as $column) {
        $row[$column->name] = in_array($column->type, ['int', 'bigint', 'tinyint', 'decimal', 'double'], true) ? 0 : 'QA';
    }

    DB::table('affiliations')->insert([...$row, 'code' => 'QA-BD-'.uniqid(), 'status' => 'ACTIVA', 'activated_at' => '10/03/2031', 'created_at' => '2031-03-10 10:00:00', 'updated_at' => '2031-03-10 10:00:00', ...$attributes]);
}

it('muestra las estadísticas del negocio al analista y conserva la cartera propia del account manager', function (): void {
    bdActingAs();
    $page = new Dashboard;

    expect($page->getWidgets())->toContain(BusinessKpiTiles::class, BusinessVenezuelaMap::class, BusinessTeamRanking::class, IncomeTrendChart::class, SalesByAgentRanking::class)
        ->not->toContain(StatsOverview::class)
        ->and($page->getColumns())->toBe(['default' => 1, 'xl' => 12])
        ->and($page->getHeader())->not->toBeNull();

    bdActingAs(['is_accountManagers' => 1]);

    expect($page->getWidgets())->toContain(StatsOverview::class)
        ->not->toContain(BusinessKpiTiles::class, BusinessVenezuelaMap::class, SalesByAgentRanking::class)
        ->and($page->getHeader())->toBeNull();
});

it('los widgets viejos no quedan registrados en el panel: nunca salen junto a los nuevos', function (): void {
    $panelWidgets = Filament::getPanel('business')->getWidgets();

    expect($panelWidgets)->toContain(BusinessKpiTiles::class)
        ->not->toContain(StatsOverview::class, TwoStatsOverview::class, StatsOverviewSaleBusiness::class, StatsOverviewSaleUsdVesBusiness::class, AffiliationIndividualChart::class, IndividualQuoteChart::class, SalesForAgency::class);
});

it('el analista ve solo lo nuevo y el account manager solo lo suyo al dibujar la página', function (): void {
    bdActingAs();
    $analyst = Livewire::test(Dashboard::class)->html();

    expect($analyst)->toContain('business-kpi-tiles')->not->toContain('stats-overview-sale-business')->not->toContain('business.widgets.two-stats-overview');

    bdActingAs(['is_accountManagers' => 1]);
    $manager = Livewire::test(Dashboard::class)->html();

    expect($manager)->toContain('two-stats-overview')->not->toContain('business-kpi-tiles');
});

it('pinta cada widget propio con datos reales del período', function (string $widget, string $text): void {
    bdActingAs();

    Livewire::test($widget, ['pageFilters' => bdFilters()])->assertOk()->assertSee($text);
})->with([
    'indicadores' => [BusinessKpiTiles::class, 'Afiliaciones nuevas'],
    'equipo' => [BusinessTeamRanking::class, 'Nuevas ventas del equipo'],
    'mapa' => [BusinessVenezuelaMap::class, 'Nuevas'],
    'nuevas frente a renovaciones' => [BusinessNewVsRenewalTiles::class, 'Dinero de ventas nuevas'],
]);

it('las filas de indicadores no llevan Conversión ni Sin clasificar', function (): void {
    bdActingAs();

    Livewire::test(BusinessKpiTiles::class, ['pageFilters' => bdFilters()])->assertSee('Afiliaciones nuevas')->assertDontSee('Conversión');
    Livewire::test(BusinessNewVsRenewalTiles::class, ['pageFilters' => bdFilters()])->assertSee('Afiliaciones renovadas')->assertDontSee('Sin clasificar');
});

it('la página de Negocios abre para el analista', function (): void {
    bdActingAs();

    Livewire::test(Dashboard::class)->assertOk()->assertSee('Negocios');
});

it('el mapa de Negocios cambia servicios médicos por afiliaciones nuevas', function (): void {
    expect(array_keys(BusinessDashboardMetrics::mapLayers(bdPeriod())))->toBe(['income', 'new_affiliations', 'affiliations', 'network']);
});

it('cuenta la producción por persona del equipo, sin mayúsculas ni tildes, y deja fuera a los agentes', function (): void {
    $id = DB::table('users')->insertGetId(['name' => 'Ángela QA Negocios', 'email' => 'qa.angela.'.uniqid().'@tudrencasa.com', 'password' => 'x', 'status' => 'ACTIVO', 'departament' => '["NEGOCIOS"]']);

    bdQuote('individual_quotes', 'ANGELA  QA NEGOCIOS');
    bdQuote('individual_quotes', 'Ángela QA Negocios');
    bdQuote('corporate_quotes', 'Ángela QA Negocios');
    bdQuote('individual_quotes', 'Agente Externo QA');

    $ranking = BusinessDashboardMetrics::teamRanking(bdPeriod());

    expect($ranking['rows'])->toHaveCount(1)
        ->and($ranking['rows'][0])->toMatchArray(['label' => 'Ángela QA Negocios', 'value' => 0, 'meta' => '3 cot. · US$ 0 nuevos'])
        ->and($ranking['total'])->toBe(0)
        ->and($id)->toBeGreaterThan(0);
});

it('un usuario inactivo no entra al ranking del equipo', function (): void {
    DB::table('users')->insert(['name' => 'Inactiva QA', 'email' => 'qa.inactiva.'.uniqid().'@tudrencasa.com', 'password' => 'x', 'status' => 'INACTIVO', 'departament' => '["NEGOCIOS"]']);
    bdQuote('individual_quotes', 'Inactiva QA');

    expect(BusinessDashboardMetrics::teamRanking(bdPeriod())['rows'])->toBe([]);
});

it('la afiliación eliminada no suma en altas, activas ni mapa', function (): void {
    $stateId = (int) DB::table('states')->where('definition', 'ZULIA')->value('id');
    expect($stateId)->toBeGreaterThan(0);

    bdInsertAffiliation(['state_id_ti' => $stateId]);
    bdInsertAffiliation(['state_id_ti' => $stateId, 'deleted_at' => '2031-03-11 10:00:00']);

    $period = bdPeriod();

    expect(CorporatePortfolioMetrics::newByState($period->from, $period->to))->toBe(['Zulia' => 1.0])
        ->and(CorporatePortfolioMetrics::kpis($period)['current']['new'])->toBe(1)
        ->and(DB::table('affiliations')->whereNull('deleted_at')->where('status', 'ACTIVA')->where('state_id_ti', $stateId)->count()
            + DB::table('affiliation_corporates')->whereNull('deleted_at')->where('status', 'ACTIVA')->where('state_id', $stateId)->count())
        ->toBe((int) (CorporatePortfolioMetrics::activeByState()['Zulia'] ?? 0));
});

/**
 * @param  array<string, mixed>  $attributes
 */
function bdSale(?string $code, float $amount, string $createdAt): void
{
    $required = DB::table('information_schema.COLUMNS')
        ->whereRaw('TABLE_SCHEMA = DATABASE()')
        ->where('TABLE_NAME', 'sales')
        ->where('IS_NULLABLE', 'NO')
        ->whereNull('COLUMN_DEFAULT')
        ->where('EXTRA', 'not like', '%auto_increment%')
        ->get(['COLUMN_NAME as name', 'DATA_TYPE as type']);
    $row = [];

    foreach ($required as $column) {
        $row[$column->name] = in_array($column->type, ['int', 'bigint', 'tinyint', 'decimal', 'double'], true) ? 0 : 'QA';
    }

    DB::table('sales')->insert([...$row, 'affiliation_code' => $code, 'total_amount' => $amount, 'created_at' => $createdAt, 'updated_at' => $createdAt]);
}

it('separa dinero nuevo, de renovación y sin clasificar, y las tres partes suman el ingreso', function (): void {
    bdInsertAffiliation(['code' => 'QA-NUEVA', 'activated_at' => '10/03/2031', 'created_by' => 'Ángela QA Negocios']);
    bdInsertAffiliation(['code' => 'QA-CUOTA', 'activated_at' => '20/12/2030', 'created_at' => '2030-12-20 10:00:00']);
    bdInsertAffiliation(['code' => 'QA-LIMITE', 'activated_at' => '15/03/2030', 'created_at' => '2030-03-15 10:00:00']);
    bdInsertAffiliation(['code' => 'QA-VIEJA', 'activated_at' => '01/02/2028', 'created_at' => '2028-02-01 10:00:00']);
    DB::table('users')->insert(['name' => 'Ángela QA Negocios', 'email' => 'qa.angela.'.uniqid().'@tudrencasa.com', 'password' => 'x', 'status' => 'ACTIVO', 'departament' => '["NEGOCIOS"]']);

    bdSale('QA-NUEVA', 100, '2031-03-10 12:00:00');
    bdSale('QA-CUOTA', 40, '2031-03-11 12:00:00');
    bdSale('QA-LIMITE', 25, '2031-03-15 00:00:00');
    bdSale('QA-VIEJA', 300, '2031-03-12 12:00:00');
    bdSale(null, 7, '2031-03-12 12:00:00');

    $period = bdPeriod();
    $summary = BusinessNewVsRenewalMetrics::summary($period->from, $period->to);
    $income = CorporateFinanceMetrics::summary($period->from, $period->to)['income'];
    $series = BusinessNewVsRenewalMetrics::incomeSeries($period);

    expect($summary)->toMatchArray(['new_income' => 140.0, 'renewal_income' => 325.0, 'unclassified_income' => 7.0, 'new_share' => 30.1])
        ->and($summary['new_income'] + $summary['renewal_income'] + $summary['unclassified_income'])->toBe($income)
        ->and(array_sum($series['first']))->toBe(140.0)
        ->and(array_sum($series['second']))->toBe(325.0)
        ->and(BusinessDashboardMetrics::teamRanking($period)['rows'][0]['meta'])->toBe('0 cot. · US$ 100 nuevos');
});

it('cuenta cada renovación una vez aunque el historial traiga una fila por afiliado', function (): void {
    foreach ([1, 2, 3] as $affiliate) {
        $required = DB::table('information_schema.COLUMNS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'affiliation_corporate_renovation_histories')
            ->where('IS_NULLABLE', 'NO')
            ->whereNull('COLUMN_DEFAULT')
            ->where('EXTRA', 'not like', '%auto_increment%')
            ->get(['COLUMN_NAME as name', 'DATA_TYPE as type']);
        $row = [];

        foreach ($required as $column) {
            $row[$column->name] = match (true) {
                in_array($column->type, ['int', 'bigint', 'smallint', 'tinyint', 'decimal', 'double'], true) => 0,
                in_array($column->type, ['date', 'datetime', 'timestamp'], true) => '2031-03-05',
                default => 'QA',
            };
        }

        DB::table('affiliation_corporate_renovation_histories')->insert([...$row, 'affiliate_corporate_id' => $affiliate, 'code_affiliation' => 'QA-COR', 'accepted_at' => '2031-03-05 10:00:00']);
    }

    $period = bdPeriod();

    expect(BusinessNewVsRenewalMetrics::summary($period->from, $period->to)['renewed'])->toBe(1)
        ->and(array_sum(BusinessNewVsRenewalMetrics::countSeries($period)['second']))->toBe(1.0);
});

it('sin datos el peso de lo nuevo queda vacío, sin dividir entre cero', function (): void {
    expect(BusinessNewVsRenewalMetrics::summary(CarbonImmutable::parse('2033-01-01'), CarbonImmutable::parse('2033-01-31'))['new_share'])->toBeNull();
});

it('no agrupa por expresión: producción no acepta ese GROUP BY', function (string $file): void {
    expect((string) file_get_contents(app_path('Support/Business/Dashboard/'.$file)))->not->toContain('groupByRaw(');
})->with(['BusinessDashboardMetrics.php', 'BusinessNewVsRenewalMetrics.php']);
