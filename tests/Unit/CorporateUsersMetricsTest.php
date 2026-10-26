<?php

declare(strict_types=1);

use App\Filament\Metrics\Pages\Dashboard;
use App\Filament\Metrics\Pages\Usuarios;
use App\Filament\Metrics\Widgets\Corporate\CorporateAreaPulse;
use App\Filament\Metrics\Widgets\Corporate\CorporateDetailWidget;
use App\Filament\Metrics\Widgets\Corporate\LeastActiveUsersRanking;
use App\Filament\Metrics\Widgets\Corporate\NetworkActiveUsersRanking;
use App\Filament\Metrics\Widgets\Corporate\NetworkByTypeChart;
use App\Filament\Metrics\Widgets\Corporate\TopActiveUsersRanking;
use App\Filament\Metrics\Widgets\Corporate\TopProductiveUsersRanking;
use App\Filament\Metrics\Widgets\Corporate\UsersAccountsChart;
use App\Filament\Metrics\Widgets\Corporate\UsersActivityHeatmap;
use App\Filament\Metrics\Widgets\Corporate\UsersComparisonTable;
use App\Filament\Metrics\Widgets\Corporate\UsersDepartmentHoursChart;
use App\Filament\Metrics\Widgets\Corporate\UsersDepartmentRecordsChart;
use App\Filament\Metrics\Widgets\Corporate\UsersHoursTrendChart;
use App\Filament\Metrics\Widgets\Corporate\UsersInsightsWidget;
use App\Filament\Metrics\Widgets\Corporate\UsersKpiTiles;
use App\Filament\Metrics\Widgets\Corporate\UsersPanelsChart;
use App\Filament\Metrics\Widgets\Corporate\UsersTimeCompositionChart;
use App\Models\User;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Metrics\Corporate\CorporateDetail;
use App\Support\Metrics\Corporate\CorporateMetrics;
use App\Support\Metrics\Corporate\CorporateUsersMetrics;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod as Period;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Escribe en base de datos: todo ocurre dentro de una transacción que siempre se
 * revierte. Las fechas (2031) no chocan con la actividad real.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    CorporateMetrics::flush();
    Filament::setCurrentPanel('metrics');

    test()->actingAs(User::factory()->make([
        'id' => 999_999_072,
        'name' => 'QA METRICAS USUARIOS',
        'email' => 'qa.metricas.usuarios@tudrencasa.com',
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
function filtrosUsuariosMarzo2031(): array
{
    return ['period' => Period::CUSTOM, 'from' => '2031-03-01', 'to' => '2031-03-31', 'compare' => true];
}

function periodoUsuariosMarzo2031(): Period
{
    return Period::fromFilters(filtrosUsuariosMarzo2031(), CarbonImmutable::parse('2031-04-15'));
}

/**
 * @param  array<string, mixed>  $attributes
 */
function usuarioQa(string $name, string $email, array $attributes = []): int
{
    return (int) DB::table('users')->insertGetId($attributes + [
        'name' => $name,
        'email' => $email,
        'password' => 'x',
        'status' => 'ACTIVO',
        'created_at' => '2025-01-01 00:00:00',
        'updated_at' => '2025-01-01 00:00:00',
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function diaQa(int $userId, string $date, int $active, array $attributes = []): void
{
    $hourly = array_fill(0, 24, 0);
    $hourly[9] = $active;

    DB::table('user_activity_days')->insert($attributes + [
        'user_id' => $userId,
        'activity_date' => $date,
        'first_minute' => 540,
        'last_minute' => 540 + $active,
        'online_minutes' => $active + 30,
        'active_minutes' => $active,
        'idle_minutes' => 10,
        'background_minutes' => 20,
        'hourly_active' => json_encode($hourly),
        'created_at' => $date.' 23:00:00',
        'updated_at' => $date.' 23:00:00',
    ]);
}

/**
 * Personal: Ana (Administración, se conecta 2 días y crea 2 registros) y Beto
 * (Operaciones, nunca se conecta). Red comercial: un agente con 30 min.
 *
 * @return array{ana: int, beto: int, agente: int}
 */
function sembrarUsuariosMarzo2031(): array
{
    $ana = usuarioQa('QA ANA  PRUEBA', 'qa.ana.usuarios@tudrencasa.com', ['departament' => json_encode(['ADMINISTRACION'])]);
    $beto = usuarioQa('QA BETO PRUEBA', 'qa.beto.usuarios@tudrencasa.com', ['departament' => json_encode(['OPERACIONES'])]);
    $agente = usuarioQa('QA AGENTE PRUEBA', 'qa.agente.usuarios@example.com', ['is_agent' => true]);

    /** Una cuenta inactiva con el mismo nombre no debe quedarse con los registros de Ana. */
    usuarioQa('QA ANA PRUEBA', 'qa.ana.vieja@example.com', ['status' => 'INACTIVO']);

    diaQa($ana, '2031-03-10', 120, ['workday_kind' => 'workday', 'work_base_minutes' => 480, 'work_active_minutes' => 120, 'overtime_minutes' => 15]);
    diaQa($ana, '2031-03-11', 60, ['workday_kind' => 'workday', 'work_base_minutes' => 480, 'work_active_minutes' => 60]);
    diaQa($agente, '2031-03-12', 30);

    DB::table('affiliations')->insert(['code' => 'QA-USR-1', 'code_agency' => 'TDG-100', 'status' => 'ACTIVA', 'created_by' => '  qa ana   prueba ', 'created_at' => '2031-03-10 10:00:00', 'updated_at' => '2031-03-10 10:00:00']);
    DB::table('individual_quotes')->insert(['code' => 'QA-USR-Q1', 'created_by' => 'QA ANA PRUEBA', 'created_at' => '2031-03-11 10:00:00', 'updated_at' => '2031-03-11 10:00:00']);

    return ['ana' => $ana, 'beto' => $beto, 'agente' => $agente];
}

it('mide uso, jornada y productividad por persona y separa al personal de la red comercial', function (): void {
    $ids = sembrarUsuariosMarzo2031();
    $period = periodoUsuariosMarzo2031();

    $staff = CorporateUsersMetrics::people($period);
    $network = CorporateUsersMetrics::people($period, CorporateUsersMetrics::NETWORK);
    $ana = $staff[$ids['ana']];

    expect($ana['department'])->toBe('Administración')
        ->and($ana['days'])->toBe(2)
        ->and($ana['active'])->toBe(180)
        ->and($ana['avg_day'])->toBe(90)
        ->and($ana['work_usage'])->toBe(19)
        ->and($ana['overtime'])->toBe(15)
        ->and($ana['records'])->toBe(2)
        ->and($ana['sources'])->toBe(['affiliations' => 1, 'quotes' => 1])
        ->and($ana['records_per_hour'])->toBe(0.7)
        ->and($staff[$ids['beto']]['active'])->toBe(0)
        ->and($staff)->not->toHaveKey($ids['agente'])
        ->and($network[$ids['agente']]['active'])->toBe(30)
        ->and($network)->not->toHaveKey($ids['beto']);

    $kpis = CorporateUsersMetrics::kpis($period);

    expect($kpis['current']['people'])->toBe(1)
        ->and($kpis['current']['hours'])->toBe(3.0)
        ->and($kpis['current']['avg_day_hours'])->toBe(1.5)
        ->and($kpis['current']['records'])->toBe(2)
        ->and($kpis['previous']['people'])->toBe(0);
});

it('los rankings ordenan por horas activas y productividad; el de menor uso incluye a quien no se conectó', function (): void {
    $ids = sembrarUsuariosMarzo2031();
    $period = periodoUsuariosMarzo2031();

    $top = CorporateUsersMetrics::topActive($period);
    $productive = CorporateUsersMetrics::topProductive($period);
    $least = CorporateUsersMetrics::leastActive($period, 1000);
    $network = CorporateUsersMetrics::topActive($period, CorporateUsersMetrics::NETWORK);

    expect($top['rows'][0])->toMatchArray(['label' => 'QA ANA  PRUEBA', 'value' => 3.0, 'key' => (string) $ids['ana']])
        ->and($top['rows'][0]['meta'])->toBe('Administración · 1 h 30 min por día')
        ->and($productive['rows'][0])->toMatchArray(['key' => (string) $ids['ana'], 'value' => 2])
        ->and(collect($least['rows'])->firstWhere('key', (string) $ids['beto'])['meta'])->toBe('Operaciones · sin conexión')
        ->and($least['rows'][0]['value'])->toBe(0.0)
        ->and($least['others'])->toBe(0)
        ->and($network['rows'][0]['key'])->toBe((string) $ids['agente'])
        ->and(CorporateUsersMetrics::avgHoursByDepartment($period))->toBe(['Administración' => 1.5])
        ->and(CorporateUsersMetrics::recordsByDepartment($period))->toBe(['Administración' => 2])
        ->and(CorporateUsersMetrics::heatmap($period)['matrix'][0][9])->toBe(120)
        ->and(CorporateUsersMetrics::timeComposition($period))->toBe(['Activo' => 3.0, 'Inactivo' => 0.3, 'En otra pestaña' => 0.7]);
});

it('la comparativa ubica a cada persona frente a su área y a la empresa', function (): void {
    $ids = sembrarUsuariosMarzo2031();
    $comparison = CorporateUsersMetrics::comparison(periodoUsuariosMarzo2031());
    $ana = collect($comparison['rows'])->firstWhere('user_id', $ids['ana']);
    $beto = collect($comparison['rows'])->firstWhere('user_id', $ids['beto']);

    expect($comparison['rows'][0]['user_id'])->toBe($ids['ana'])
        ->and($ana['vs_area'])->toEqual(0)
        ->and($ana['vs_company'])->toEqual(0)
        ->and($ana['last_seen'])->toBe('2031-03-11')
        ->and($beto['vs_area'])->toBeNull()
        ->and($comparison['company_avg_day'])->toBe(90);
});

it('la lectura avisa uso bajo de la jornada y cuentas internas sin uso', function (): void {
    $ids = sembrarUsuariosMarzo2031();
    $insights = collect(CorporateUsersMetrics::insights(periodoUsuariosMarzo2031()));

    expect($insights->pluck('title')->implode(' | '))->toContain('menos del 50 % de su jornada')
        ->and($insights->first(fn (array $insight): bool => str_contains($insight['title'], 'de su jornada'))['text'])->toContain('QA ANA  PRUEBA (19 %)')
        ->and($insights->first()['tone'])->toBe('warning')
        ->and(collect(CorporateUsersMetrics::idleAccounts())->pluck('user_id'))->toContain($ids['beto'])
        ->and(collect(CorporateUsersMetrics::idleAccounts())->pluck('user_id'))->not->toContain($ids['agente']);
});

it('avisa cuando el período o su comparación caen antes de la medición', function (): void {
    $before = Period::fromFilters(['period' => Period::CUSTOM, 'from' => '2026-10-01', 'to' => '2026-10-10', 'compare' => true], CarbonImmutable::parse('2026-10-25'));
    $after = Period::fromFilters(['period' => Period::CUSTOM, 'from' => '2026-10-09', 'to' => '2026-10-15', 'compare' => true], CarbonImmutable::parse('2026-10-25'));

    expect(collect(CorporateUsersMetrics::insights($before))->pluck('title'))->toContain('La medición empezó el 08/10/2026')
        ->and(collect(CorporateUsersMetrics::insights($after))->pluck('title'))->toContain('El período anterior es previo a la medición');
});

it('el detalle lista los días de una persona y la gente de un área', function (): void {
    $ids = sembrarUsuariosMarzo2031();
    $period = periodoUsuariosMarzo2031();

    $days = CorporateDetail::fetch($period, 'user_person', (string) $ids['ana']);
    $area = CorporateDetail::fetch($period, 'user_department', 'Administración');

    expect($days['total'])->toBe(2)
        ->and($days['rows'][0])->toMatchArray(['date' => '11/03/2031', 'active' => '1 h', 'work_usage' => '13 %', 'records' => '1'])
        ->and($days['rows'][1])->toMatchArray(['date' => '10/03/2031', 'active' => '2 h', 'overtime' => '15 min', 'records' => '1'])
        ->and($area['total'])->toBeGreaterThanOrEqual(1)
        ->and($area['rows'][0])->toMatchArray(['name' => 'QA ANA  PRUEBA', 'active' => '3 h', 'avg_day' => '1 h 30 min', 'records' => '2'])
        ->and(CorporateDetail::label('user_person', (string) $ids['ana']))->toBe('Actividad de QA ANA  PRUEBA')
        ->and(CorporateDetail::noun('user_department'))->toBe('personas');

    Livewire::test(CorporateDetailWidget::class, ['pageFilters' => filtrosUsuariosMarzo2031()])
        ->call('openDetail', 'user_person', (string) $ids['ana'])
        ->assertSee('Actividad de QA ANA  PRUEBA')
        ->assertSee('Primera conexión');
});

it('la página y cada widget se renderizan con datos y vacíos', function (): void {
    sembrarUsuariosMarzo2031();
    $filters = filtrosUsuariosMarzo2031();

    Livewire::test(Usuarios::class)->assertSuccessful()->assertSee('Volver al resumen')->assertSee('Modo presentación');
    Livewire::test(UsersKpiTiles::class, ['pageFilters' => $filters])->assertSee('Personas conectadas')->assertSee('Registros creados')->assertSee('cuentas internas sin uso');
    Livewire::test(TopActiveUsersRanking::class, ['pageFilters' => $filters])->assertSee('Top 10 de uso del sistema')->assertSee('QA ANA  PRUEBA')->assertSee('3,0 h');
    Livewire::test(TopProductiveUsersRanking::class, ['pageFilters' => $filters])->assertSee('Top 10 de productividad')->assertSee('cotizaciones');
    Livewire::test(LeastActiveUsersRanking::class, ['pageFilters' => $filters])->assertSee('sin conexión')->assertDontSee('Otros:');
    Livewire::test(NetworkActiveUsersRanking::class, ['pageFilters' => $filters])->assertSee('QA AGENTE PRUEBA');
    Livewire::test(UsersInsightsWidget::class, ['pageFilters' => $filters])->assertSee('Lectura de las personas');
    Livewire::test(UsersComparisonTable::class, ['pageFilters' => $filters])->assertSee('Comparativa por persona')->assertSee('QA BETO PRUEBA', false);
    Livewire::test(UsersActivityHeatmap::class, ['pageFilters' => $filters])->assertSee('Cuándo se trabaja en el sistema')->assertDontSee("dimension: 'slot'", false);
    Livewire::test(CorporateAreaPulse::class, ['pageFilters' => $filters])->assertSee('Usuarios')->assertSee('Horas activas');

    foreach ([UsersHoursTrendChart::class, UsersDepartmentHoursChart::class, UsersDepartmentRecordsChart::class, UsersTimeCompositionChart::class, UsersPanelsChart::class, NetworkByTypeChart::class, UsersAccountsChart::class] as $chart) {
        Livewire::test($chart, ['pageFilters' => $filters])->assertSuccessful();
        expect((string) Livewire::test($chart, ['pageFilters' => $filters])->instance()->getOptionsForTest())->not->toContain('"');
    }

    $empty = ['period' => Period::CUSTOM, 'from' => '2032-03-01', 'to' => '2032-03-31', 'compare' => false];

    Livewire::test(TopActiveUsersRanking::class, ['pageFilters' => $empty])->assertSee('No hay actividad registrada en este período.');
    Livewire::test(TopProductiveUsersRanking::class, ['pageFilters' => $empty])->assertSee('El personal no creó registros en este período.');
    Livewire::test(UsersActivityHeatmap::class, ['pageFilters' => $empty])->assertSee('No hay actividad registrada en este período.');
});

it('la página tiene permiso propio, va después de Organización y su lectura llega al resumen ejecutivo', function (): void {
    sembrarUsuariosMarzo2031();

    expect(DepartmentNavigationPermissionRegistry::slugsFor(Usuarios::class))->toBe(['metricas-usuarios'])
        ->and(DepartmentNavigationPermissionRegistry::moduleFor(Usuarios::class))->toBe('METRICAS')
        ->and(Usuarios::getNavigationSort())->toBeGreaterThan(\App\Filament\Metrics\Pages\Organizacion::getNavigationSort())
        ->and(Usuarios::canAccess())->toBeTrue()
        ->and(file_get_contents(dirname(__DIR__, 2).'/app/Providers/Filament/MetricsPanelProvider.php'))->toContain('Organizacion::class, Usuarios::class]');

    $summary = collect(\App\Support\Metrics\Corporate\CorporateInsights::for(periodoUsuariosMarzo2031(), 20));

    expect($summary->where('area', 'Usuarios')->count())->toBeBetween(1, 2)
        ->and($summary->where('area', 'Usuarios')->pluck('tone')->all())->not->toContain('neutral');

    Livewire::test(Dashboard::class)->assertSuccessful();
});
