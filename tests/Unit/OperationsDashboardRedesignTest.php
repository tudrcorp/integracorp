<?php

declare(strict_types=1);

use App\Filament\Operations\Pages\DashboardOperaciones;
use App\Filament\Operations\Widgets\Dashboard\OperationsAgeChart;
use App\Filament\Operations\Widgets\Dashboard\OperationsBusinessLineSplit;
use App\Filament\Operations\Widgets\Dashboard\OperationsDashboardDetail;
use App\Filament\Operations\Widgets\Dashboard\OperationsKpiTiles;
use App\Filament\Operations\Widgets\Dashboard\OperationsServicesStatusChart;
use App\Filament\Operations\Widgets\Dashboard\OperationsServicesTrendChart;
use App\Filament\Operations\Widgets\Dashboard\OperationsServiceTypeChart;
use App\Filament\Operations\Widgets\Dashboard\OperationsStateRanking;
use App\Filament\Operations\Widgets\Dashboard\OperationsTopPatientsRanking;
use App\Filament\Operations\Widgets\Dashboard\OperationsTopServicesRanking;
use App\Filament\Operations\Widgets\Dashboard\OperationsWorkloadHeatmap;
use App\Models\OperationServiceStatistic;
use App\Models\User;
use App\Support\Operations\Dashboard\OperationsDashboardAnalytics as Analytics;
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
    Analytics::flush();
    Filament::setCurrentPanel('operations');

    test()->actingAs(User::factory()->make([
        'id' => 999_999_061,
        'name' => 'QA DASHBOARD',
        'email' => 'qa.dashboard@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
        'supplier_id' => null,
    ]));
});

afterEach(function (): void {
    DB::rollBack();
    Analytics::flush();
});

/**
 * @return array<string, mixed>
 */
function filtrosMarzo2031(bool $compare = true): array
{
    return ['period' => Period::CUSTOM, 'from' => '2031-03-01', 'to' => '2031-03-31', 'compare' => $compare];
}

function servicioEstadistico(array $attributes): OperationServiceStatistic
{
    return OperationServiceStatistic::query()->create($attributes + [
        'source_type' => 'qa',
        'source_id' => random_int(1, 999_999),
        'service_status' => 'PENDIENTE',
        'service_type' => 'LABORATORIOS',
        'business_line' => 'CORPORATIVOS',
        'state' => 'ZULIA',
        'patient_name' => 'PACIENTE QA',
        'patient_document' => 'V-QA-1',
        'patient_age' => 40,
        'started_at_time' => '10:15:00',
        'telemedicine_case_id' => 900_001,
        'case_code' => 'QA-CASO',
        'specific_service' => 'HEMATOLOGIA QA',
    ]);
}

function sembrarMarzo2031(): void
{
    // Marzo 2031: 4 servicios (2 finalizados, 1 en gestión, 1 pendiente).
    servicioEstadistico(['service_on' => '2031-03-03', 'service_status' => 'FINALIZADO']);
    servicioEstadistico(['service_on' => '2031-03-03', 'service_status' => 'FINALIZADO', 'service_type' => 'MEDICAMENTOS', 'business_line' => 'INDIVIDUALES', 'patient_document' => 'V-QA-2', 'patient_name' => 'OTRO QA', 'patient_age' => 8, 'telemedicine_case_id' => 900_002]);
    servicioEstadistico(['service_on' => '2031-03-10', 'service_status' => 'EN GESTION', 'state' => 'MIRANDA']);
    servicioEstadistico(['service_on' => null, 'started_on' => '2031-03-20', 'started_at_time' => '22:40:00']);

    // Febrero 2031 (período anterior): 2 servicios, ninguno finalizado.
    servicioEstadistico(['service_on' => '2031-02-05']);
    servicioEstadistico(['service_on' => '2031-02-06']);
}

it('el período del mes en curso se compara con el mismo tramo del mes anterior', function (): void {
    $period = Period::fromFilters(null, CarbonImmutable::parse('2031-03-31'));

    expect($period->preset)->toBe(Period::THIS_MONTH)
        ->and($period->from->toDateString())->toBe('2031-03-01')
        ->and($period->to->toDateString())->toBe('2031-03-31')
        ->and($period->previousFrom->toDateString())->toBe('2031-02-01')
        ->and($period->previousTo->toDateString())->toBe('2031-02-28')
        ->and($period->compare)->toBeTrue()
        ->and($period->granularity())->toBe(Period::GRANULARITY_DAY);

    $mid = Period::fromFilters(['period' => Period::THIS_MONTH], CarbonImmutable::parse('2031-03-24'));

    expect($mid->previousTo->toDateString())->toBe('2031-02-24')
        ->and($mid->label())->toBe('1–24 mar 2031')
        ->and($mid->previousLabel())->toBe('1–24 feb 2031');
});

it('resuelve los atajos, el rango personalizado y la granularidad', function (): void {
    $today = CarbonImmutable::parse('2031-03-15');

    $last7 = Period::fromFilters(['period' => Period::LAST_7], $today);
    expect($last7->from->toDateString())->toBe('2031-03-09')
        ->and($last7->previousTo->toDateString())->toBe('2031-03-08')
        ->and($last7->previousFrom->toDateString())->toBe('2031-03-02');

    $swapped = Period::fromFilters(['period' => Period::CUSTOM, 'from' => '2031-03-10', 'to' => '2031-01-01'], $today);
    expect($swapped->from->toDateString())->toBe('2031-01-01')
        ->and($swapped->to->toDateString())->toBe('2031-03-10')
        ->and($swapped->granularity())->toBe(Period::GRANULARITY_WEEK);

    $huge = Period::fromFilters(['period' => Period::CUSTOM, 'from' => '2020-01-01', 'to' => '2031-03-15'], $today);
    expect($huge->days())->toBe(Period::MAX_DAYS)
        ->and($huge->granularity())->toBe(Period::GRANULARITY_MONTH);

    expect(Period::fromFilters(['period' => 'inventado', 'compare' => '0'], $today))
        ->preset->toBe(Period::THIS_MONTH)
        ->compare->toBeFalse();
});

it('calcula los indicadores del período y del anterior', function (): void {
    sembrarMarzo2031();
    $kpis = Analytics::kpis(Period::fromFilters(filtrosMarzo2031()));

    expect($kpis['current'])->toBe(['services' => 4, 'finished' => 2, 'open' => 2, 'close_rate' => 50.0, 'patients' => 2, 'cases' => 2])
        ->and($kpis['previous']['services'])->toBe(2)
        ->and($kpis['previous']['finished'])->toBe(0)
        ->and($kpis['sparklines']['services'])->toHaveCount(31)
        ->and($kpis['sparklines']['services'][2])->toBe(2)
        ->and(array_sum($kpis['sparklines']['finished']))->toBe(2);
});

it('agrupa por estatus, tipo, línea, estado, edad, servicio y paciente', function (): void {
    sembrarMarzo2031();
    $period = Period::fromFilters(filtrosMarzo2031());

    expect(Analytics::byStatus($period))->toBe(['PENDIENTE' => 1, 'EN GESTION' => 1, 'FINALIZADO' => 2])
        ->and(Analytics::byServiceType($period))->toBe(['LABORATORIOS' => 3, 'MEDICAMENTOS' => 1])
        ->and(Analytics::byBusinessLine($period))->toBe(['CORPORATIVOS' => 3, 'INDIVIDUALES' => 1])
        ->and(Analytics::byState($period, 1))->toBe(['rows' => [['label' => 'ZULIA', 'value' => 3]], 'others' => 1, 'total' => 4])
        ->and(Analytics::ageBands($period)['0–12'])->toBe(1)
        ->and(Analytics::ageBands($period)['30–44'])->toBe(1)
        ->and(Analytics::topServices($period, 5)['rows'][0])->toBe(['label' => 'HEMATOLOGIA QA', 'value' => 4])
        ->and(Analytics::topPatients($period, 1)[0])->toMatchArray(['key' => 'V-QA-1', 'label' => 'PACIENTE QA', 'value' => 3, 'cases' => 1]);
});

it('alinea la tendencia con el período anterior y arma el mapa de calor', function (): void {
    sembrarMarzo2031();
    $period = Period::fromFilters(filtrosMarzo2031());
    $trend = Analytics::trend($period);
    $heatmap = Analytics::heatmap($period);

    expect($trend['labels'])->toHaveCount(31)
        ->and($trend['labels'][0])->toBe('1 mar')
        ->and($trend['current'][2])->toBe(2)
        // Rango personalizado de 31 días: el anterior son los 31 días previos (29 ene – 28 feb).
        ->and($trend['previous_labels'][0])->toBe('29 ene')
        ->and($trend['previous'][7])->toBe(1)
        ->and($trend['previous_labels'][7])->toBe('5 feb')
        ->and($heatmap['total'])->toBe(4)
        ->and($heatmap['matrix'][0][10])->toBe(3)
        ->and($heatmap['matrix'][3][22])->toBe(1)
        ->and($heatmap['max'])->toBe(3);
});

it('el detalle filtra por corte, valida la dimensión y cuenta el total', function (): void {
    sembrarMarzo2031();
    $period = Period::fromFilters(filtrosMarzo2031());

    expect(Analytics::detail($period, 'service_status', 'FINALIZADO')['total'])->toBe(2)
        ->and(Analytics::detail($period, 'state', 'MIRANDA')['rows'][0]['place'])->toBe('MIRANDA')
        ->and(Analytics::detail($period, 'slot', '0-10')['total'])->toBe(3)
        ->and(Analytics::detail($period, 'age_band', '0–12')['total'])->toBe(1)
        ->and(Analytics::detail($period, 'patient', 'V-QA-2')['total'])->toBe(1)
        ->and(Analytics::detail($period, 'all', '')['total'])->toBe(4)
        ->and(Analytics::detail($period, 'all', '', 2)['rows'])->toHaveCount(2)
        ->and(Analytics::detail($period, 'service_status', 'FINALIZADO')['rows'][0])->toHaveKeys(['case_code', 'patient', 'service', 'status', 'date'])
        ->and(Analytics::detail($period, 'columna_inventada', 'x'))->toBe(['rows' => [], 'total' => 0])
        ->and(Analytics::detail($period, 'slot', '9-99'))->toBe(['rows' => [], 'total' => 0])
        ->and(Analytics::detail($period, 'age_band', 'no-existe'))->toBe(['rows' => [], 'total' => 0]);
});

it('las variaciones y la mini tendencia de los KPI se calculan bien', function (): void {
    expect(OperationsKpiTiles::delta(120, 100))->toBe(['direction' => 'up', 'text' => '+20 %'])
        ->and(OperationsKpiTiles::delta(95, 100))->toBe(['direction' => 'down', 'text' => '−5,0 %'])
        ->and(OperationsKpiTiles::delta(5, 0))->toBe(['direction' => 'up', 'text' => 'Nuevo'])
        ->and(OperationsKpiTiles::delta(10, 10))->toBe(['direction' => 'flat', 'text' => 'Sin cambios'])
        ->and(OperationsKpiTiles::delta(55.5, 50.0, isPercent: true))->toBe(['direction' => 'up', 'text' => '+5,5 pp'])
        ->and(OperationsKpiTiles::sparklinePath([0, 0, 0]))->toBeNull()
        ->and(OperationsKpiTiles::sparklinePath([1]))->toBeNull()
        ->and(OperationsKpiTiles::sparklinePath([0, 2, 1]))->toBe('M0 30 L50 2 L100 16')
        ->and(OperationsWorkloadHeatmap::step(0, 10))->toBe(0)
        ->and(OperationsWorkloadHeatmap::step(1, 10))->toBe(1)
        ->and(OperationsWorkloadHeatmap::step(10, 10))->toBe(7);
});

it('la página y cada widget se renderizan con datos', function (): void {
    sembrarMarzo2031();
    $filters = filtrosMarzo2031();

    Livewire::test(DashboardOperaciones::class)
        ->assertSuccessful()
        ->assertSee('Modo presentación');

    Livewire::test(OperationsKpiTiles::class, ['pageFilters' => $filters])
        ->assertSee('Servicios registrados')
        ->assertSee('50,0 %')
        ->assertSee('+100 %');

    foreach ([OperationsServicesTrendChart::class, OperationsServiceTypeChart::class, OperationsAgeChart::class] as $chart) {
        Livewire::test($chart, ['pageFilters' => $filters])->assertSuccessful();
    }

    Livewire::test(OperationsServicesStatusChart::class, ['pageFilters' => $filters])
        ->assertSee('FINALIZADO')
        ->assertSee('50,0 %');

    Livewire::test(OperationsBusinessLineSplit::class, ['pageFilters' => $filters])->assertSee('INDIVIDUALES')->assertSee('25,0 %');
    Livewire::test(OperationsStateRanking::class, ['pageFilters' => $filters])->assertSee('MIRANDA');
    Livewire::test(OperationsTopServicesRanking::class, ['pageFilters' => $filters])->assertSee('HEMATOLOGIA QA');
    Livewire::test(OperationsTopPatientsRanking::class, ['pageFilters' => $filters])->assertSee('PACIENTE QA')->assertSee('1 caso');
    Livewire::test(OperationsWorkloadHeatmap::class, ['pageFilters' => $filters])->assertSee('Tramo más cargado')->assertSee('Lunes, 10:00');
});

it('los widgets vacíos muestran un estado claro en vez de gráficos vacíos', function (): void {
    $filters = ['period' => Period::CUSTOM, 'from' => '2032-07-01', 'to' => '2032-07-31'];

    Livewire::test(OperationsServicesStatusChart::class, ['pageFilters' => $filters])->assertSee('No hay servicios en este período.');
    Livewire::test(OperationsWorkloadHeatmap::class, ['pageFilters' => $filters])->assertSee('No hay servicios con hora de inicio');
    Livewire::test(OperationsStateRanking::class, ['pageFilters' => $filters])->assertSee('No hay servicios en este período.');
});

it('el clic abre el detalle y rechaza dimensiones desconocidas', function (): void {
    sembrarMarzo2031();
    $filters = filtrosMarzo2031();

    Livewire::test(OperationsDashboardDetail::class, ['pageFilters' => $filters])
        ->call('openDetail', 'service_type', 'MEDICAMENTOS')
        ->assertDispatched('open-modal', id: OperationsDashboardDetail::MODAL_ID)
        ->assertSee('Tipo de servicio: MEDICAMENTOS')
        ->assertSee('OTRO QA')
        ->assertDontSee('PACIENTE QA');

    Livewire::test(OperationsDashboardDetail::class, ['pageFilters' => $filters])
        ->call('openDetail', 'telemedicine_case_id; DROP', 'x')
        ->assertNotDispatched('open-modal')
        ->assertSet('dimension', null);
});

it('las opciones de los gráficos no llevan comillas dobles que rompan el atributo HTML', function (): void {
    $filters = filtrosMarzo2031();

    foreach ([OperationsServicesTrendChart::class, OperationsServicesStatusChart::class, OperationsServiceTypeChart::class, OperationsAgeChart::class] as $chart) {
        $options = (string) Livewire::test($chart, ['pageFilters' => $filters])->instance()->getOptionsForTest();

        expect($options)->not->toContain('"')
            ->toContain('ops = {');
    }
});

it('el atributo del gráfico llega completo al navegador, sin código impreso como texto', function (): void {
    sembrarMarzo2031();
    $filters = filtrosMarzo2031();

    foreach ([OperationsServicesTrendChart::class, OperationsServicesStatusChart::class, OperationsServiceTypeChart::class, OperationsAgeChart::class] as $chart) {
        $html = Livewire::test($chart, ['pageFilters' => $filters])->html();

        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $canvas = (new DOMXPath($document))->query('//*[@data-chart-type]')->item(0);

        expect($canvas)->not->toBeNull("{$chart} sin lienzo")
            ->and($canvas->getAttribute('x-data'))->toContain('type:')
            ->and($canvas->getAttribute('x-data'))->toEndWith('})')
            ->and($document->textContent)->not->toContain('statusRoles')
            ->and($document->textContent)->not->toContain('maintainAspectRatio');
    }
});

it('cada gráfico tiene altura explícita y el de tipos crece con sus categorías', function (): void {
    sembrarMarzo2031();
    $filters = filtrosMarzo2031();

    $heights = [];

    foreach ([OperationsServicesTrendChart::class, OperationsServicesStatusChart::class, OperationsServiceTypeChart::class, OperationsAgeChart::class] as $chart) {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.Livewire::test($chart, ['pageFilters' => $filters])->html());
        $canvas = (new DOMXPath($document))->query('//*[@data-chart-type]')->item(0);

        expect($canvas->getAttribute('style'))->toMatch('/height: \d+px/');
        $heights[$chart] = $canvas->getAttribute('style');
    }

    // Dos tipos de servicio → altura mínima; con muchos tipos crece hasta el tope.
    expect($heights[OperationsServiceTypeChart::class])->toBe('height: 180px')
        ->and(Livewire::test(OperationsServiceTypeChart::class, ['pageFilters' => $filters])->instance()->getOptionsForTest())->toContain('autoSkip: false');
});

it('las tarjetas se estiran a la altura de su fila para no dejar huecos', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/dashboard/page-start.blade.php'))
        ->toContain(".fi-wi-widget > .fi-section {\n        height: 100%;");
});

it('el modo presentación oculta el ticker de tickets del helpdesk', function (): void {
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/dashboard/page-start.blade.php');
    $ticker = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/business-helpdesk-tickets-ticker.blade.php');

    // Los selectores deben seguir existiendo en el ticker: si se renombran, el ticker reaparecería.
    expect($css)->toContain('html.ops-presenting [wire\:key="business-helpdesk-tickets-ticker-root"]')
        ->toContain('html.ops-presenting .tickets-ticker-wrap')
        ->and($ticker)->toContain('wire:key="business-helpdesk-tickets-ticker-root"')
        ->toContain('tickets-ticker-wrap');
});

it('el mapa de calor tiene un tooltip propio con contexto y celdas vacías que lo muestran', function (): void {
    sembrarMarzo2031();

    $component = Livewire::test(OperationsWorkloadHeatmap::class, ['pageFilters' => filtrosMarzo2031()]);
    $html = $component->html();

    // 4 servicios en 2 tramos con actividad → promedio 2 por tramo.
    expect($component->instance()->getViewDataForTest()['average'])->toBe(2.0)
        ->and($html)->toContain('role="tooltip"')
        ->toContain('data-range="10:00 – 11:00"')
        ->toContain('data-value="3"')
        ->toContain('× un tramo promedio')
        ->toContain('Clic para ver los servicios de este tramo')
        ->toContain('Sin servicios en este tramo')
        ->toContain('aria-disabled="true"')
        ->not->toContain('x-tooltip')
        ->not->toMatch('/<button[^>]*role="gridcell"[^>]*\sdisabled[\s>=]/');
});

it('los cuatro gráficos usan el tooltip propio del dashboard', function (): void {
    $filters = filtrosMarzo2031();

    foreach ([OperationsServicesTrendChart::class, OperationsServicesStatusChart::class, OperationsServiceTypeChart::class, OperationsAgeChart::class] as $chart) {
        $options = Livewire::test($chart, ['pageFilters' => $filters])->instance()->getOptionsForTest();

        expect($options)->toContain('enabled: false')
            ->toContain('external: (context) => renderTip(context,')
            ->toContain('textContent')
            ->not->toContain('innerHTML');
    }

    expect(Livewire::test(OperationsServicesTrendChart::class, ['pageFilters' => $filters])->instance()->getOptionsForTest())
        ->toContain('frente al período anterior');
});

it('el tooltip de los widgets en Blade se posiciona con un objeto de estilos', function (): void {
    $partial = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/dashboard/partials/tooltip.blade.php');

    // Un string en x-bind:style lo pisa x-show; las clases de translate las pisa la transición.
    expect($partial)->toContain("x-bind:style=\"{ left: (tip ? tip.x : 0) + 'px', top: (tip ? tip.y : 0) + 'px', transform: 'translate(-50%, -100%)' }\"")
        ->not->toContain('-translate-x-1/2')
        ->not->toContain('translate-y-1');

    foreach (['heatmap', 'business-line'] as $view) {
        expect(file_get_contents(dirname(__DIR__, 2)."/resources/views/filament/operations/dashboard/{$view}.blade.php"))
            ->toContain("@include('filament.operations.dashboard.partials.tooltip')")
            ->not->toContain('x-tooltip');
    }
});

it('el tooltip se posiciona respecto de su contenedor, no de la celda que lo dispara', function (): void {
    foreach (['heatmap' => 'cell', 'business-line' => 'segment'] as $view => $param) {
        $blade = file_get_contents(dirname(__DIR__, 2)."/resources/views/filament/operations/dashboard/{$view}.blade.php");

        // En Alpine, $el dentro del método es el elemento que dispara el evento (la celda),
        // no el contenedor: el tooltip terminaba en la esquina (incidente del 24/10/2026).
        expect($blade)->toContain('data-tip-host')
            ->toContain("const host = {$param}.closest('[data-tip-host]').getBoundingClientRect();")
            ->not->toContain('this.$el.getBoundingClientRect()');
    }
});

it('el encabezado del Dashboard de Operaciones es el compacto: contexto, período, filtro y acciones en una barra', function (): void {
    Livewire::test(DashboardOperaciones::class)
        ->assertSuccessful()
        ->assertSee('corp-header-filters', false)
        ->assertSee('Operaciones')
        ->assertSee('Comparar')
        ->assertSee('Modo presentación')
        ->assertDontSee('Haga clic en cualquier gráfico')
        ->set('filters.period', Period::CUSTOM)
        ->assertSee('Desde')
        ->assertSee('Hasta');
});
