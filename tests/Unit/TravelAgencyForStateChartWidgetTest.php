<?php

declare(strict_types=1);

use App\Filament\Business\Resources\TravelAgencies\Pages\ListTravelAgencies;
use App\Filament\Business\Resources\TravelAgencies\Widgets\TravelAgencyForStateChart;
use App\Models\TravelAgency;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * sqlite `:memory:` propia: la prueba nunca toca la base de desarrollo.
 */
beforeEach(function (): void {
    config()->set('database.connections.travel_chart_testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]);
    $this->previousConnection = config('database.default');
    config()->set('database.default', 'travel_chart_testing');
    DB::purge('travel_chart_testing');
    DB::setDefaultConnection('travel_chart_testing');

    expect(DB::connection()->getDriverName())->toBe('sqlite');

    Schema::create('states', function (Blueprint $table): void {
        $table->id();
        $table->string('definition');
    });
    Schema::create('travel_agencies', function (Blueprint $table): void {
        $table->id();
        /** En producción es varchar: guarda IDs del catálogo y nombres escritos a mano. */
        $table->string('state_id')->nullable();
    });
});

afterEach(function (): void {
    DB::purge('travel_chart_testing');
    config()->set('database.default', $this->previousConnection);
    DB::setDefaultConnection($this->previousConnection);
});

function travelChartWidget(): TravelAgencyForStateChart
{
    /** El widget real, pero leyendo de una consulta fija en vez de la tabla de la página. */
    $widget = Mockery::mock(TravelAgencyForStateChart::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $widget->shouldReceive('getPageTableQuery')->andReturnUsing(static fn (): Builder => TravelAgency::query());

    return $widget;
}

function travelChartCall(TravelAgencyForStateChart $widget, string $method): mixed
{
    return (new ReflectionMethod($widget, $method))->invoke($widget);
}

function seedTravelAgencies(array $byState, int $withoutState = 0): void
{
    foreach ($byState as $name => $count) {
        $stateId = DB::table('states')->insertGetId(['definition' => $name]);

        foreach (range(1, $count) as $i) {
            DB::table('travel_agencies')->insert(['state_id' => $stateId]);
        }
    }

    for ($i = 0; $i < $withoutState; $i++) {
        DB::table('travel_agencies')->insert(['state_id' => null]);
    }
}

it('es un gráfico de barras horizontales a ancho completo, ligado al listado', function (): void {
    $ref = new ReflectionClass(TravelAgencyForStateChart::class);
    $widget = new TravelAgencyForStateChart;

    expect($ref->getDefaultProperties()['columnSpan'] ?? null)->toBe('full')
        ->and(travelChartCall($widget, 'getType'))->toBe('bar')
        ->and(travelChartCall($widget, 'getTablePage'))->toBe(ListTravelAgencies::class);
});

it('ordena de mayor a menor, deja «Sin estado» al final en gris y calcula porcentajes', function (): void {
    seedTravelAgencies(['ARAGUA' => 11, 'DISTRITO CAPITAL' => 39, 'CARABOBO' => 10, 'LARA' => 1], withoutState: 2);

    $data = travelChartCall(travelChartWidget(), 'getData');
    $dataset = $data['datasets'][0];

    expect($data['labels'])->toBe(['DISTRITO CAPITAL', 'ARAGUA', 'CARABOBO', 'LARA', 'Sin estado'])
        ->and($dataset['data'])->toBe([39, 11, 10, 1, 2])
        ->and($dataset['percentages'])->toBe([61.9, 17.5, 15.9, 1.6, 3.2])
        ->and($dataset['muted'])->toBe([false, false, false, false, true])
        ->and($dataset['levels'])->toBe([4, 2, 2, 0, 0])
        ->and($dataset['rampLight'])->toHaveCount(6)
        ->and($dataset['rampDark'])->toHaveCount(6);
});

it('une en una sola barra el ID del catálogo y el nombre escrito a mano', function (): void {
    $capitalId = DB::table('states')->insertGetId(['definition' => 'DISTRITO CAPITAL']);
    $araguaId = DB::table('states')->insertGetId(['definition' => 'ARAGUA']);

    foreach ([
        ...array_fill(0, 3, (string) $capitalId),
        'Distrito Capital', 'Estado Distrito Capital', '  distrito   capital ',
        (string) $araguaId, 'Estado Aragua',
        'Departamento de Norte de Santander',
        '999',
        '', '   ', null,
    ] as $stateId) {
        DB::table('travel_agencies')->insert(['state_id' => $stateId]);
    }

    $data = travelChartCall(travelChartWidget(), 'getData');
    $dataset = $data['datasets'][0];

    expect($data['labels'])->toBe(['DISTRITO CAPITAL', 'ARAGUA', 'DEPARTAMENTO DE NORTE DE SANTA...', 'Estado #999', 'Sin estado'])
        ->and($dataset['data'])->toBe([6, 2, 1, 1, 3])
        ->and($dataset['muted'])->toBe([false, false, false, false, true])
        ->and(array_sum($dataset['data']))->toBe(13);
});

it('el color sube de intensidad con la cantidad y nunca se sale de la rampa', function (): void {
    seedTravelAgencies(['A' => 1, 'B' => 4, 'C' => 9, 'D' => 16, 'E' => 25, 'F' => 100]);

    $levels = travelChartCall(travelChartWidget(), 'getData')['datasets'][0]['levels'];

    expect($levels)->toBe([4, 2, 2, 1, 1, 0])
        ->and(max($levels))->toBeLessThan(5)
        ->and(min($levels))->toBeGreaterThanOrEqual(0);
});

it('la descripción explica el total y quién concentra más', function (): void {
    seedTravelAgencies(['DISTRITO CAPITAL' => 39, 'ARAGUA' => 11], withoutState: 2);

    expect(travelChartWidget()->getDescription())
        ->toBe('52 agencias en 2 estados. DISTRITO CAPITAL concentra el 75 % (39). 2 sin estado asignado (barra gris): conviene completarlo en la ficha. De mayor a menor; respeta la búsqueda y los filtros del listado.');
});

it('sin agencias lo dice en vez de dibujar un gráfico vacío', function (): void {
    $widget = travelChartWidget();

    expect($widget->getDescription())->toBe('No hay agencias con la búsqueda y los filtros actuales.')
        ->and(travelChartCall($widget, 'getData')['labels'])->toBe(['Sin agencias']);
});

it('se lee en modo claro y oscuro, sin leyenda y con los números junto a cada estado', function (): void {
    seedTravelAgencies(['ARAGUA' => 3]);

    $options = travelChartCall(travelChartWidget(), 'getOptions');

    expect($options)->toBeInstanceOf(RawJs::class)
        ->and((string) $options)
        ->toContain("indexAxis: 'y'")
        ->toContain('legend: { display: false }')
        ->toContain("classList.contains('dark')")
        ->toContain("count + ' · '")
        ->toContain('ds.rampDark')
        ->toContain('ds.rampLight')
        ->not->toContain('datalabels');
});
