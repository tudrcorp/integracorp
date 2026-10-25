<?php

declare(strict_types=1);

use App\Filament\Metrics\Pages\Cartera;
use App\Filament\Metrics\Pages\Comercial;
use App\Filament\Metrics\Pages\Dashboard;
use App\Filament\Metrics\Pages\Finanzas;
use App\Filament\Metrics\Pages\OperacionesYSalud;
use App\Filament\Metrics\Pages\Organizacion;
use App\Filament\Metrics\Widgets\Corporate\CommercialKpiTiles;
use App\Filament\Metrics\Widgets\Corporate\CorporateAreaPulse;
use App\Filament\Metrics\Widgets\Corporate\CorporateDetailWidget;
use App\Filament\Metrics\Widgets\Corporate\CorporateFunnel;
use App\Filament\Metrics\Widgets\Corporate\CorporateInsightsWidget;
use App\Filament\Metrics\Widgets\Corporate\CorporateVenezuelaMap;
use App\Filament\Metrics\Widgets\Corporate\ExecutiveKpiTiles;
use App\Filament\Metrics\Widgets\Corporate\FinanceKpiTiles;
use App\Filament\Metrics\Widgets\Corporate\IncomeByAgencyRanking;
use App\Filament\Metrics\Widgets\Corporate\IncomeByPlanChart;
use App\Filament\Metrics\Widgets\Corporate\IncomeByTypeChart;
use App\Filament\Metrics\Widgets\Corporate\IncomeTrendChart;
use App\Filament\Metrics\Widgets\Corporate\IncomeVsCostsChart;
use App\Filament\Metrics\Widgets\Corporate\LivesByAgeChart;
use App\Filament\Metrics\Widgets\Corporate\NewVsDropoutsChart;
use App\Filament\Metrics\Widgets\Corporate\OrganizationKpiTiles;
use App\Filament\Metrics\Widgets\Corporate\PortfolioKpiTiles;
use App\Filament\Metrics\Widgets\Corporate\PortfolioStatusChart;
use App\Filament\Metrics\Widgets\Corporate\QuotesTrendChart;
use App\Filament\Metrics\Widgets\Corporate\SalesByAgentRanking;
use App\Filament\Metrics\Widgets\Corporate\TicketsFlowChart;
use App\Filament\Metrics\Widgets\Corporate\TicketsStatusChart;
use App\Filament\Metrics\Widgets\Corporate\TicketsTypeChart;
use App\Filament\Operations\Widgets\Dashboard\OperationsDashboardDetail;
use App\Models\User;
use App\Support\Metrics\Corporate\CorporateCommercialMetrics;
use App\Support\Metrics\Corporate\CorporateDetail;
use App\Support\Metrics\Corporate\CorporateFinanceMetrics;
use App\Support\Metrics\Corporate\CorporateGeography;
use App\Support\Metrics\Corporate\CorporateInsights;
use App\Support\Metrics\Corporate\CorporateMapMetrics;
use App\Support\Metrics\Corporate\CorporateMetrics;
use App\Support\Metrics\Corporate\CorporateOrganizationMetrics;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod as Period;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Escribe en base de datos: todo ocurre dentro de una transacción que siempre se
 * revierte. Las fechas (2031) no chocan con datos reales.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    CorporateMetrics::flush();
    Filament::setCurrentPanel('metrics');

    test()->actingAs(User::factory()->make([
        'id' => 999_999_071,
        'name' => 'QA METRICAS',
        'email' => 'qa.metricas@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'METRICAS'],
    ]));
});

afterEach(function (): void {
    DB::rollBack();
    CorporateMetrics::flush();
});

/**
 * @return array<string, mixed>
 */
function filtrosCorporativosMarzo2031(bool $compare = true): array
{
    return ['period' => Period::CUSTOM, 'from' => '2031-03-01', 'to' => '2031-03-31', 'compare' => $compare];
}

function periodoCorporativoMarzo2031(bool $compare = true): Period
{
    return Period::fromFilters(filtrosCorporativosMarzo2031($compare), CarbonImmutable::parse('2031-04-15'));
}

function ventaQa(string $date, float $amount, array $attributes = []): void
{
    DB::table('sales')->insert($attributes + [
        'code_agency' => 'QA-AG-1',
        'owner_code' => 'QA-AG-1',
        'date_activation' => $date,
        'invoice_number' => 'QA-'.random_int(100_000, 999_999),
        'persons' => 1,
        'affiliation_code' => 'QA-IND-1',
        'affiliate_full_name' => 'TITULAR QA',
        'type' => 'AFILIACION INDIVIDUAL',
        'total_amount' => $amount,
        'created_at' => $date.' 10:00:00',
        'updated_at' => $date.' 10:00:00',
    ]);
}

function ticketQa(string $date, array $attributes = []): void
{
    DB::table('help_desks')->insert($attributes + [
        'description' => 'Ticket QA',
        'priority' => 'ALTA',
        'status' => 'EN PROCESO',
        'ticket_type' => 'incident',
        'created_at' => $date.' 08:00:00',
        'updated_at' => $date.' 08:00:00',
    ]);
}

function sembrarEmpresaMarzo2031(): void
{
    $miranda = (int) DB::table('states')->whereRaw("UPPER(definition) = 'MIRANDA'")->value('id');

    DB::table('affiliations')->insert([
        'code' => 'QA-IND-1',
        'code_agency' => 'QA-AG-1',
        'status' => 'ACTIVA',
        'state_id_ti' => $miranda,
        'full_name_ti' => 'TITULAR QA',
        'created_at' => '2031-03-02 09:00:00',
        'updated_at' => '2031-03-02 09:00:00',
    ]);

    ventaQa('2031-03-03', 300);
    ventaQa('2031-03-10', 100, ['type' => 'AFILIACION CORPORATIVA', 'affiliation_code' => 'QA-COR-1', 'affiliate_full_name' => 'EMPRESA QA']);
    ventaQa('2031-02-05', 200);

    DB::table('individual_quotes')->insert([
        ['code' => 'QA-COT-1', 'created_by' => 'QA', 'status' => 'EJECUTADA', 'full_name' => 'CLIENTE QA 1', 'created_at' => '2031-03-04 10:00:00', 'updated_at' => '2031-03-04 10:00:00'],
        ['code' => 'QA-COT-2', 'created_by' => 'QA', 'status' => 'PRE-APROBADA', 'full_name' => 'CLIENTE QA 2', 'created_at' => '2031-03-05 10:00:00', 'updated_at' => '2031-03-05 10:00:00'],
        ['code' => 'QA-COT-3', 'created_by' => 'QA', 'status' => 'ANULADA', 'full_name' => 'CLIENTE QA 3', 'created_at' => '2031-03-06 10:00:00', 'updated_at' => '2031-03-06 10:00:00'],
        ['code' => 'QA-COT-4', 'created_by' => 'QA', 'status' => 'ANULADA', 'full_name' => 'CLIENTE QA 4', 'created_at' => '2031-03-07 10:00:00', 'updated_at' => '2031-03-07 10:00:00'],
    ]);

    ticketQa('2031-03-02', ['status' => 'TERMINADO', 'resolved_at' => '2031-03-02 20:00:00']);
    ticketQa('2031-03-03', ['status' => 'TERMINADO', 'updated_at' => '2031-03-04 08:00:00']);
    ticketQa('2031-03-05', ['priority' => 'MEDIA', 'ticket_type' => null, 'sla_breached_at' => '2031-03-06 08:00:00']);
}

it('calcula ingresos, ticket y margen del período y del anterior', function (): void {
    sembrarEmpresaMarzo2031();
    $period = periodoCorporativoMarzo2031();

    $kpis = CorporateFinanceMetrics::kpis($period);

    expect($kpis['current']['income'])->toBe(400.0)
        ->and($kpis['current']['payments'])->toBe(2)
        ->and($kpis['current']['average_ticket'])->toBe(200.0)
        ->and($kpis['previous']['income'])->toBe(200.0)
        ->and(array_sum($kpis['sparklines']['income']))->toBe(400.0)
        ->and(CorporateFinanceMetrics::incomeByType($period))->toBe(['Afiliación individual' => 300.0, 'Afiliación corporativa' => 100.0])
        ->and(CorporateFinanceMetrics::incomeByPlan($period))->toHaveKey(CorporateFinanceMetrics::CORPORATE_PLAN_LABEL)
        ->and(CorporateFinanceMetrics::incomeByAgency($period)['rows'][0]['key'])->toBe('QA-AG-1');
});

it('el ingreso se ubica en el estado de la afiliación pagada', function (): void {
    sembrarEmpresaMarzo2031();
    $layers = CorporateMapMetrics::layers(periodoCorporativoMarzo2031());

    expect($layers['income']['values']['Miranda'] ?? null)->toBe(300.0)
        ->and($layers['income']['unknown'])->toBe(100.0)
        ->and($layers['income']['total'])->toBe(400.0)
        ->and($layers['income']['money'])->toBeTrue()
        ->and($layers['affiliations']['snapshot'])->toBeTrue()
        ->and($layers['affiliations']['previous'])->toBeNull()
        ->and(array_keys($layers))->toBe(CorporateMapMetrics::LAYERS);
});

it('normaliza los nombres de estado del catálogo hacia las claves del mapa', function (): void {
    expect(CorporateGeography::geoKey('ANZOATEGUI'))->toBe('Anzoátegui')
        ->and(CorporateGeography::geoKey('  distrito capital '))->toBe('Distrito Capital')
        ->and(CorporateGeography::geoKey('ATLÁNTIDA'))->toBeNull()
        ->and(CorporateGeography::byGeoKeyFromNames(['MIRANDA' => 2, 'Miranda' => 3, 'XYZ' => 1]))
        ->toBe(['Miranda' => 5.0, CorporateGeography::UNKNOWN => 1.0]);
});

it('mide la conversión y el embudo de las cotizaciones individuales', function (): void {
    sembrarEmpresaMarzo2031();
    $period = periodoCorporativoMarzo2031();

    $summary = CorporateCommercialMetrics::summary($period->from, $period->to);
    $funnel = CorporateCommercialMetrics::funnel($period);

    expect($summary['individual_quotes'])->toBe(4)
        ->and($summary['executed'])->toBe(1)
        ->and($summary['conversion'])->toBe(25.0)
        ->and(array_column($funnel, 'value'))->toBe([4, 2, 1]);
});

it('el helpdesk cuenta resueltos con o sin fecha de resolución y el SLA del período', function (): void {
    sembrarEmpresaMarzo2031();
    $period = periodoCorporativoMarzo2031();

    $summary = CorporateOrganizationMetrics::summary($period->from, $period->to);

    expect($summary['created'])->toBe(3)
        ->and($summary['resolved'])->toBe(2)
        ->and($summary['sla_breached'])->toBe(1)
        ->and($summary['sla_rate'])->toBe(66.7)
        ->and($summary['resolution_hours'])->toBe(18.0)
        ->and(CorporateOrganizationMetrics::byPriority($period))->toBe(['ALTA' => 2, 'MEDIA' => 1])
        ->and(CorporateOrganizationMetrics::byType($period))->toBe(['Incidente' => 2, 'Sin clasificar' => 1]);
});

it('la caché se invalida aunque la clave de versión no exista todavía', function (): void {
    $period = periodoCorporativoMarzo2031();

    expect(CorporateFinanceMetrics::kpis($period)['current']['income'])->toBe(0.0);

    ventaQa('2031-03-03', 50);
    CorporateMetrics::flush();

    expect(CorporateFinanceMetrics::kpis($period)['current']['income'])->toBe(50.0);
});

it('la lectura automática ordena por urgencia y explica cada cifra', function (): void {
    sembrarEmpresaMarzo2031();

    $insights = CorporateInsights::for(periodoCorporativoMarzo2031());
    $order = ['critical' => 0, 'warning' => 1, 'good' => 2, 'neutral' => 3];
    $ranks = array_map(fn (array $insight): int => $order[$insight['tone']], $insights);

    expect($insights)->not->toBeEmpty()
        ->and($ranks)->toBe(collect($ranks)->sort()->values()->all())
        ->and(collect($insights)->pluck('title')->implode(' '))->toContain('Los ingresos crecen 100 %');
});

it('el detalle lista los registros del corte, cuenta el total y rechaza dimensiones desconocidas', function (): void {
    sembrarEmpresaMarzo2031();
    $period = periodoCorporativoMarzo2031();

    $byType = CorporateDetail::fetch($period, 'sale_type', 'Afiliación corporativa');
    $byState = CorporateDetail::fetch($period, 'sale_state', 'Miranda');
    $funnel = CorporateDetail::fetch($period, 'quote_funnel', 'advanced');
    $tickets = CorporateDetail::fetch($period, 'ticket_type', 'Sin clasificar');

    expect($byType['total'])->toBe(1)
        ->and($byType['rows'][0]['name'])->toBe('EMPRESA QA')
        ->and($byState['total'])->toBe(1)
        ->and($byState['rows'][0]['amount'])->toBe('US$ 300,00')
        ->and($funnel['total'])->toBe(2)
        ->and($tickets['total'])->toBe(1)
        ->and(CorporateDetail::fetch($period, 'sales; DROP', 'x'))->toBe(['rows' => [], 'total' => 0]);

    Livewire::test(CorporateDetailWidget::class, ['pageFilters' => filtrosCorporativosMarzo2031()])
        ->call('openDetail', 'sale_type', 'Afiliación corporativa')
        ->assertDispatched('open-modal', id: CorporateDetailWidget::CORPORATE_MODAL_ID)
        ->assertSee('EMPRESA QA')
        ->assertDontSee('TITULAR QA');

    Livewire::test(CorporateDetailWidget::class, ['pageFilters' => filtrosCorporativosMarzo2031()])
        ->call('openDetail', 'service_type', 'LABORATORIOS')
        ->assertNotDispatched('open-modal');
});

it('el detalle corporativo y el de operaciones usan modales distintos en la misma página', function (): void {
    expect(CorporateDetailWidget::CORPORATE_MODAL_ID)->not->toBe(OperationsDashboardDetail::MODAL_ID);

    $html = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/dashboard/detail.blade.php');

    expect($html)->toContain(':id="$this->modalId()"');
});

it('cada página y cada widget se renderizan con datos', function (): void {
    sembrarEmpresaMarzo2031();
    $filters = filtrosCorporativosMarzo2031();

    foreach ([Dashboard::class, Finanzas::class, Comercial::class, Cartera::class, OperacionesYSalud::class, Organizacion::class] as $page) {
        Livewire::test($page)->assertSuccessful()->assertSee('Modo presentación');
    }

    Livewire::test(ExecutiveKpiTiles::class, ['pageFilters' => $filters])
        ->assertSee('La empresa hoy')
        ->assertSee('US$ 400')
        ->assertSee('Foto a hoy');
    Livewire::test(FinanceKpiTiles::class, ['pageFilters' => $filters])->assertSee('Ticket promedio')->assertSee('+100 %');
    Livewire::test(CommercialKpiTiles::class, ['pageFilters' => $filters])->assertSee('25,0 %');
    Livewire::test(PortfolioKpiTiles::class, ['pageFilters' => $filters])->assertSee('vidas cubiertas');
    Livewire::test(OrganizationKpiTiles::class, ['pageFilters' => $filters])->assertSee('66,7 %');
    Livewire::test(IncomeByTypeChart::class, ['pageFilters' => $filters])->assertSee('US$ 400')->assertSee('Afiliación corporativa');
    Livewire::test(TicketsStatusChart::class, ['pageFilters' => $filters])->assertSee('TERMINADO');
    Livewire::test(IncomeByAgencyRanking::class, ['pageFilters' => $filters])->assertSee('QA-AG-1')->assertSee('US$ 400');
    Livewire::test(SalesByAgentRanking::class, ['pageFilters' => $filters])->assertSee('Venta directa (sin agente)');
    Livewire::test(CorporateFunnel::class, ['pageFilters' => $filters])->assertSee('Convertidas en afiliación')->assertSee('50,0 % de la etapa anterior');
    Livewire::test(CorporateInsightsWidget::class, ['pageFilters' => $filters])->assertSee('Lectura del período');
    Livewire::test(CorporateAreaPulse::class, ['pageFilters' => $filters])->assertSee('Pulso por área')->assertSee('Operaciones y salud');
    Livewire::test(CorporateVenezuelaMap::class, ['pageFilters' => $filters])->assertSee('Venezuela por estado')->assertSee('Ficha del estado');

    foreach ([IncomeTrendChart::class, IncomeVsCostsChart::class, IncomeByPlanChart::class, QuotesTrendChart::class, NewVsDropoutsChart::class, TicketsFlowChart::class, TicketsTypeChart::class, LivesByAgeChart::class, PortfolioStatusChart::class] as $chart) {
        Livewire::test($chart, ['pageFilters' => $filters])->assertSuccessful();
    }
});

it('el panel usa navegación superior y las páginas de área traen un botón para volver al resumen', function (): void {
    $provider = file_get_contents(dirname(__DIR__, 2).'/app/Providers/Filament/MetricsPanelProvider.php');

    expect($provider)->toContain('->topNavigation()')
        ->not->toContain('->sidebarCollapsibleOnDesktop()')
        ->and(Finanzas::getNavigationGroup())->toBeNull()
        ->and((new Finanzas)->getSubNavigation())->toBe([]);

    foreach ([Finanzas::class, Comercial::class, Cartera::class, OperacionesYSalud::class, Organizacion::class] as $page) {
        Livewire::test($page)->assertSee('Volver al resumen')->assertSee(Dashboard::getUrl());
    }

    Livewire::test(Dashboard::class)->assertDontSee('Volver al resumen');
});

it('los widgets vacíos muestran un estado claro', function (): void {
    $filters = ['period' => Period::CUSTOM, 'from' => '2032-07-01', 'to' => '2032-07-31'];

    Livewire::test(IncomeByTypeChart::class, ['pageFilters' => $filters])->assertSee('No hay ingresos en este período.');
    Livewire::test(IncomeByAgencyRanking::class, ['pageFilters' => $filters])->assertSee('No hay movimientos en este período.');
    Livewire::test(CorporateFunnel::class, ['pageFilters' => $filters])->assertSee('No hay cotizaciones en este período.');
});

it('las opciones de los gráficos no llevan comillas dobles que rompan el atributo HTML', function (): void {
    $filters = filtrosCorporativosMarzo2031();

    foreach ([IncomeTrendChart::class, IncomeVsCostsChart::class, IncomeByTypeChart::class, IncomeByPlanChart::class, TicketsFlowChart::class, TicketsStatusChart::class, TicketsTypeChart::class] as $chart) {
        $options = (string) Livewire::test($chart, ['pageFilters' => $filters])->instance()->getOptionsForTest();

        expect($options)->not->toContain('"')->toContain('ops = {');
    }
});

it('los gráficos sin detalle no prometen el clic', function (): void {
    $options = (string) Livewire::test(LivesByAgeChart::class, ['pageFilters' => filtrosCorporativosMarzo2031()])->instance()->getOptionsForTest();
    $clickable = (string) Livewire::test(IncomeByPlanChart::class, ['pageFilters' => filtrosCorporativosMarzo2031()])->instance()->getOptionsForTest();

    expect($options)->not->toContain('operations-dashboard-detail')->not->toContain('Clic para ver')
        ->and($clickable)->toContain("dimension: 'sale_plan'");
});

it('el panel registra el grupo corporativo, sus permisos y el estilo del dashboard; las páginas de la API quedan fuera del menú', function (): void {
    $provider = file_get_contents(dirname(__DIR__, 2).'/app/Providers/Filament/MetricsPanelProvider.php');
    $registry = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/DepartmentNavigationPermissionRegistry.php');

    expect($provider)->toContain('Panel corporativo · Toma de decisiones')
        ->toContain('OperacionesYSalud::class')
        ->and($registry)->toContain("'metricas-finanzas'")
        ->toContain("'metricas-organizacion'")
        ->and(Finanzas::canAccess())->toBeTrue()
        ->and(\App\Filament\Metrics\Pages\Administracion::shouldRegisterNavigation())->toBeFalse()
        ->and(\App\Filament\Metrics\Clusters\Negocios\CorretajeCluster::shouldRegisterNavigation())->toBeFalse();
});

it('los tableros agrupan con DashboardGrouping (subconsulta), la única forma que producción acepta', function (): void {
    $root = dirname(__DIR__, 2);
    $files = [
        ...glob($root.'/app/Support/Metrics/Corporate/*.php'),
        ...glob($root.'/app/Support/Operations/Dashboard/*.php'),
        $root.'/app/Support/Operations/OperationsDashboardMetrics.php',
    ];

    foreach ($files as $file) {
        $source = file_get_contents($file);

        expect($source)->not->toContain('groupByRaw(', basename($file).' agrupa por expresión: producción lo rechaza')
            ->and(preg_match("/->groupBy\\('(grp_|grouping_key|patient_key|month_number)/", $source))->toBe(0, basename($file).' agrupa por alias: producción también lo rechaza; usar DashboardGrouping::by()');
    }
});

it('en pantalla grande cada fila de cada página llena las 12 columnas, sin huecos', function (): void {
    $pages = [Dashboard::class, Finanzas::class, Comercial::class, Cartera::class, OperacionesYSalud::class, Organizacion::class];

    foreach ($pages as $page) {
        $used = 0;

        foreach ((new $page)->getWidgets() as $widget) {
            if (in_array($widget, [CorporateDetailWidget::class, OperationsDashboardDetail::class], true)) {
                continue;
            }

            $span = (fn (): int|string|array => $this->columnSpan)->call(new $widget);
            $xl = is_array($span) ? ($span['xl'] ?? $span['default'] ?? 12) : $span;
            $xl = $xl === 'full' ? 12 : (int) $xl;

            if ($used + $xl > 12) {
                expect($used)->toBe(12, class_basename($page).': fila incompleta antes de '.class_basename($widget));
                $used = 0;
            }

            $used += $xl;
            $used = $used === 12 ? 0 : $used;
        }

        expect($used)->toBe(0, class_basename($page).': la última fila queda incompleta');
    }
});

it('el organigrama va primero en la barra superior', function (): void {
    $sorts = collect([\App\Filament\Metrics\Pages\Organigrama::class, Dashboard::class, Finanzas::class, Comercial::class, Cartera::class, OperacionesYSalud::class, Organizacion::class])
        ->mapWithKeys(fn (string $page): array => [class_basename($page) => $page::getNavigationSort()])
        ->sort();

    expect($sorts->keys()->first())->toBe('Organigrama')
        ->and($sorts->keys()->all())->toBe(['Organigrama', 'Dashboard', 'Finanzas', 'Comercial', 'Cartera', 'OperacionesYSalud', 'Organizacion']);
});

it('los ingresos de una empresa aliada se heredan por la jerarquía de agencias, no por la venta', function (): void {
    $companyId = DB::table('white_companies')->insertGetId(['name' => 'ALIADA QA', 'created_at' => now(), 'updated_at' => now()]);
    $agencyType = (int) DB::table('agencies')->value('agency_type_id');

    DB::table('agencies')->insert([
        ['code' => 'QA-MASTER', 'owner_code' => 'TDG-100', 'white_company_id' => $companyId, 'agency_type_id' => $agencyType, 'email' => 'qa-master@tudrencasa.com', 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'QA-HIJA', 'owner_code' => 'QA-MASTER', 'white_company_id' => null, 'agency_type_id' => $agencyType, 'email' => 'qa-hija@tudrencasa.com', 'created_at' => now(), 'updated_at' => now()],
    ]);
    \App\Support\WhiteCompanies\WhiteCompanyOwnership::flush();

    ventaQa('2031-03-03', 120, ['code_agency' => 'qa-hija ']);
    ventaQa('2031-03-04', 80, ['code_agency' => 'QA-MASTER']);
    ventaQa('2031-03-05', 999, ['code_agency' => 'QA-OTRA']);

    $period = periodoCorporativoMarzo2031();
    $ranking = CorporateFinanceMetrics::incomeByWhiteCompany($period);

    expect($ranking['rows'])->toBe([['label' => 'ALIADA QA', 'value' => 200.0, 'key' => (string) $companyId]])
        ->and(CorporateDetail::fetch($period, 'sale_white_company', (string) $companyId)['total'])->toBe(2);

    \App\Support\WhiteCompanies\WhiteCompanyOwnership::flush();
});

it('el encabezado reúne contexto, período, filtros compactos y acciones en una sola barra', function (): void {
    Livewire::test(Comercial::class)
        ->assertSuccessful()
        ->assertSee('Panel corporativo')
        ->assertSee('corp-header-filters', false)
        ->assertSee('Comparar')
        ->assertSee('Volver al resumen')
        ->assertSee('Modo presentación')
        ->assertDontSee('Haga clic en cualquier gráfico');

    Livewire::test(Comercial::class)
        ->set('filters.period', Period::CUSTOM)
        ->assertSee('Desde')
        ->assertSee('Hasta')
        ->set('filters.period', Period::LAST_MONTH)
        ->set('filters.compare', false)
        ->assertDontSee('vs 1');
});

it('en Métricas la barra de módulos se reemplaza por «Volver a Negocios» como primer elemento del menú, en ámbar', function (): void {
    test()->actingAs(User::factory()->make([
        'id' => 999_999_075,
        'email' => 'qa.negocios@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'METRICAS', 'NEGOCIOS'],
    ]));

    $item = collect(Filament::getPanel('metrics')->getNavigationItems())
        ->first(fn ($item): bool => $item->getLabel() === 'Volver a Negocios');
    $styles = view('filament.metrics.corporate.topbar-styles')->render();

    expect($item)->not->toBeNull()
        ->and($item->getUrl())->toBe(route('filament.business.pages.dashboard'))
        ->and($item->getSort())->toBeLessThan(\App\Filament\Metrics\Pages\Organigrama::getNavigationSort())
        ->and($item->getExtraAttributes()['class'] ?? '')->toContain('corp-nav-back')
        ->and($item->isVisible())->toBeTrue()
        ->and($styles)->toContain('.corp-nav-back')
        ->toContain('rgb(245 158 11')
        ->toContain('flex-wrap: nowrap')
        ->toContain('margin-inline-start: auto')
        ->and(Filament::getPanel('metrics')->hasDatabaseNotifications())->toBeFalse();

    test()->actingAs(User::factory()->make([
        'id' => 999_999_076,
        'email' => 'externo@gmail.com',
        'status' => 'ACTIVO',
        'departament' => [],
    ]));

    expect($item->isVisible())->toBeFalse()
        ->and(file_get_contents(dirname(__DIR__, 2).'/app/Providers/Filament/MetricsPanelProvider.php'))->not->toContain('internal-quick-nav');
});

it('los estilos del menú de Métricas no se filtran a otros paneles con la navegación SPA', function (): void {
    $css = view('filament.metrics.corporate.topbar-styles')->render();
    preg_match_all('/^\s*([^@\s{}][^{}]*)\{/m', (string) preg_replace('/\/\*.*?\*\//s', '', $css), $matches);

    expect($matches[1])->not->toBeEmpty();

    foreach ($matches[1] as $selectorList) {
        foreach (explode(',', $selectorList) as $selector) {
            expect(trim($selector))->toContain('.fi-panel-metrics ');
        }
    }

    $pageStart = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/dashboard/page-start.blade.php');

    expect($pageStart)->toContain('.fi-page:has(.ops-dash) .fi-wi-widget > .fi-section')
        ->not->toMatch('/^\s*\.fi-wi-widget\s*\{/m');
});
