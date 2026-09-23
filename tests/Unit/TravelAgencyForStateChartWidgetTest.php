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
        $table->unsignedBigInteger('state_id')->nullable();
    });
});

afterEach(function (): void {
    DB::purge('travel_chart_testing');
    config()->set('database.default', $this->previousConnection);
    DB::setDefaultConnection($this->previousConnection);
});

function travelChartWidget(): TravelAgencyForStateChart
{
    return new TravelAgencyForStateChartUnderTest;
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
        ->and($dataset['colorLight'])->toBe('#2a78d6')
        ->and($dataset['colorDark'])->toBe('#3987e5');
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
        ->not->toContain('datalabels');
});

/**
 * El widget real, pero leyendo de una consulta fija en vez de la tabla de la página.
 */
final class TravelAgencyForStateChartUnderTest extends TravelAgencyForStateChart
{
    protected function getPageTableQuery(): Builder
    {
        return TravelAgency::query();
    }
}
