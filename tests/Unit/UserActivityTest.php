<?php

declare(strict_types=1);

use App\Filament\Business\Pages\UserActivityMonitor;
use App\Models\User;
use App\Models\UserActivityDay;
use App\Models\UserActivityEvent;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\LivePresence\CacheLivePresenceRepository;
use App\Support\LivePresence\LivePresenceStore;
use App\Support\LivePresence\RedisLivePresenceRepository;
use App\Support\UserActivity\UserActivityClock;
use App\Support\UserActivity\UserActivityFlusher;
use App\Support\UserActivity\UserActivityLiveBoard;
use App\Support\UserActivity\UserActivityMinutes;
use App\Support\UserActivity\UserActivityProfile;
use App\Support\UserActivity\UserActivityReport;
use App\Support\UserActivity\UserActivityRetention;
use App\Support\UserActivity\UserActivityState;
use App\Support\UserActivity\UserActivityTracker;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * La actividad en vivo va a la caché `array` (memoria) y lo que se guarda va a
 * una conexión sqlite `:memory:` propia: este test nunca toca la base real.
 */
function seedUserActivitySchema(): void
{
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('phone')->nullable();
        $table->string('status')->nullable();
        $table->text('departament')->nullable();
        $table->boolean('is_agent')->default(false);
        $table->boolean('is_subagent')->default(false);
        $table->boolean('is_agency')->default(false);
        $table->string('agency_type')->nullable();
        $table->string('code_agency')->nullable();
        $table->boolean('is_doctor')->default(false);
        $table->unsignedBigInteger('supplier_id')->nullable();
        $table->boolean('is_proveedor_amd')->default(false);
        $table->string('password')->nullable();
        $table->timestamps();
    });

    Schema::create('user_activity_days', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->date('activity_date');
        $table->unsignedSmallInteger('first_minute')->nullable();
        $table->unsignedSmallInteger('last_minute')->nullable();
        $table->unsignedSmallInteger('online_minutes')->default(0);
        $table->unsignedSmallInteger('active_minutes')->default(0);
        $table->unsignedSmallInteger('idle_minutes')->default(0);
        $table->unsignedSmallInteger('background_minutes')->default(0);
        $table->unsignedInteger('page_views')->default(0);
        $table->unsignedInteger('actions')->default(0);
        $table->unsignedInteger('downloads')->default(0);
        $table->json('hourly_active')->nullable();
        $table->text('minute_states')->nullable();
        $table->timestamps();
        $table->unique(['user_id', 'activity_date']);
    });

    Schema::create('user_activity_events', function (Blueprint $table): void {
        $table->id();
        $table->char('event_key', 36)->unique();
        $table->unsignedBigInteger('user_id');
        $table->dateTime('occurred_at');
        $table->string('type', 20);
        $table->string('label', 255);
        $table->string('panel', 60)->nullable();
        $table->string('page', 255)->nullable();
        $table->string('path', 300)->nullable();
        $table->unsignedInteger('duration_ms')->nullable();
        $table->unsignedSmallInteger('status')->nullable();
        $table->timestamp('created_at')->nullable();
    });

    Schema::create('logs', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('action')->nullable();
        $table->string('route')->nullable();
        $table->text('response')->nullable();
        $table->string('method')->nullable();
        $table->string('ip')->nullable();
        $table->text('user_agent')->nullable();
        $table->timestamps();
    });

    $base = ['departament' => null, 'is_agent' => false, 'is_agency' => false, 'agency_type' => null, 'code_agency' => null];

    DB::table('users')->insert([
        [...$base, 'id' => 501, 'name' => 'Ana Pérez', 'email' => 'ana@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => '["NEGOCIOS"]'],
        [...$base, 'id' => 502, 'name' => 'Luis Agente', 'email' => 'luis@gmail.com', 'status' => 'ACTIVO', 'is_agent' => true, 'code_agency' => 'TDG-104'],
        [...$base, 'id' => 503, 'name' => 'Agencia Sol', 'email' => 'sol@agencia.com', 'status' => 'ACTIVO', 'is_agency' => true, 'agency_type' => 'MASTER', 'code_agency' => 'TDG-200'],
    ]);
}

beforeEach(function (): void {
    config([
        'live-presence.enabled' => true,
        'live-presence.store' => 'cache',
        'live-presence.cache_store' => 'array',
        'live-presence.activity.enabled' => true,
        'live-presence.activity.idle_after_seconds' => 300,
        'live-presence.geoip.database' => '/no/existe.mmdb',
        'session.driver' => 'array',
        'database.connections.user_activity_testing' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
    ]);

    $this->previousConnection = config('database.default');
    config()->set('database.default', 'user_activity_testing');
    DB::purge('user_activity_testing');
    DB::setDefaultConnection('user_activity_testing');

    expect(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(DB::connection()->getDatabaseName())->toBe(':memory:');

    seedUserActivitySchema();
    Cache::store('array')->flush();
    LivePresenceStore::swap(null);

    /** Miércoles 8 de octubre de 2026, 10:00 en Caracas. */
    $this->travelTo(CarbonImmutable::parse('2026-10-08 10:00:00', 'America/Caracas'));
});

afterEach(function (): void {
    LivePresenceStore::swap(null);
    DB::purge('user_activity_testing');
    config()->set('database.default', $this->previousConnection);
    DB::setDefaultConnection($this->previousConnection);
});

function activityStore(): CacheLivePresenceRepository
{
    $store = new CacheLivePresenceRepository(Cache::store('array'), 300, 50, 3600, 100);
    LivePresenceStore::swap($store);

    return $store;
}

function activityNow(int $plusSeconds = 0): int
{
    return now()->getTimestamp() + $plusSeconds;
}

function todayKey(): string
{
    return UserActivityClock::dayKey(UserActivityClock::now());
}

function minuteNow(int $plusMinutes = 0): int
{
    return UserActivityClock::minuteOfDay(UserActivityClock::now()) + $plusMinutes;
}

function actingAsActivityUser(array $departments, int $id = 900): User
{
    $user = User::factory()->make(['name' => 'Supervisor', 'email' => 'super'.$id.'@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => $departments]);
    $user->id = $id;
    $user->setRelation('permissions', new EloquentCollection);
    test()->actingAs($user);

    return $user;
}

it('clasifica el estado: oculto, inactivo tras 5 minutos sin interacción o activo', function (): void {
    expect(UserActivityTracker::stateFor(false, 0))->toBe(UserActivityState::Background)
        ->and(UserActivityTracker::stateFor(true, 299_999))->toBe(UserActivityState::Active)
        ->and(UserActivityTracker::stateFor(true, 300_000))->toBe(UserActivityState::Idle)
        ->and(UserActivityTracker::stateFor(true, null))->toBe(UserActivityState::Active);
});

it('un latido marca el minuto y el estado en vivo del usuario', function (): void {
    $store = activityStore();

    UserActivityTracker::recordPing(501, 'tabA', true, 4_000, 'heartbeat', ['panel' => 'Negocios', 'page' => 'Afiliaciones'], activityNow());

    $live = UserActivityTracker::liveState($store, 501, activityNow());

    expect($store->activityMinutes(501, todayKey()))->toBe([minuteNow() => 'a'])
        ->and($store->activityUsers(todayKey()))->toBe([501])
        ->and($live['state'])->toBe(UserActivityState::Active)
        ->and($live['page'])->toBe('Afiliaciones')
        ->and($live['last_interaction_at'])->toBe(activityNow() - 4);
});

it('rellena el hueco entre latidos de una pestaña oculta con su estado anterior', function (): void {
    $store = activityStore();

    UserActivityTracker::recordPing(501, 'tabA', false, null, 'heartbeat', [], activityNow());
    UserActivityTracker::recordPing(501, 'tabA', false, null, 'heartbeat', [], activityNow(150));

    expect($store->activityMinutes(501, todayKey()))->toBe([
        minuteNow() => 'b',
        minuteNow(1) => 'b',
        minuteNow(2) => 'b',
    ]);
});

it('un hueco largo no se rellena: la pestaña pudo estar cerrada', function (): void {
    $store = activityStore();

    UserActivityTracker::recordPing(501, 'tabA', true, 0, 'heartbeat', [], activityNow());
    UserActivityTracker::recordPing(501, 'tabA', true, 0, 'heartbeat', [], activityNow(20 * 60));

    expect(array_keys($store->activityMinutes(501, todayKey())))->toBe([minuteNow(), minuteNow(20)]);
});

it('registra cuándo quedó inactivo y cuándo volvió a trabajar', function (): void {
    $store = activityStore();

    UserActivityTracker::recordPing(501, 'tabA', true, 0, 'heartbeat', [], activityNow());
    UserActivityTracker::recordPing(501, 'tabA', true, 301_000, 'idle', [], activityNow(301));
    UserActivityTracker::recordPing(501, 'tabA', true, 0, 'activity', [], activityNow(900));

    $events = $store->peekActivityEvents(10);

    expect(array_column($events, 'type'))->toBe(['idle', 'active'])
        ->and($events[0]['label'])->toBe('Quedó inactivo: sin teclado ni mouse por 5 min')
        ->and($events[0]['occurred_at'])->toBe(CarbonImmutable::createFromTimestamp(activityNow(), 'America/Caracas')->format('Y-m-d H:i:s'))
        ->and($events[1]['label'])->toBe('Volvió a usar el sistema')
        ->and(UserActivityTracker::liveState($store, 501, activityNow(900))['state'])->toBe(UserActivityState::Active);
});

it('con dos pestañas manda la más activa, y el minuto activo gana al de otra pestaña', function (): void {
    $store = activityStore();

    UserActivityTracker::recordPing(501, 'tabOculta', false, null, 'heartbeat', [], activityNow());
    UserActivityTracker::recordPing(501, 'tabVisible', true, 1_000, 'heartbeat', ['page' => 'Cotizaciones'], activityNow(5));

    $live = UserActivityTracker::liveState($store, 501, activityNow(10));

    expect($live['state'])->toBe(UserActivityState::Active)
        ->and($live['tabs'])->toBe(2)
        ->and($live['page'])->toBe('Cotizaciones')
        ->and($store->activityMinutes(501, todayKey()))->toBe([minuteNow() => 'a']);
});

it('una pestaña que dejó de latir ya no cuenta: el usuario queda desconectado', function (): void {
    $store = activityStore();

    UserActivityTracker::recordPing(501, 'tabA', true, 0, 'heartbeat', [], activityNow());

    expect(UserActivityTracker::liveState($store, 501, activityNow(UserActivityTracker::TAB_STALE_SECONDS + 1))['state'])->toBe(UserActivityState::Offline);
});

it('las páginas y acciones cuentan como minuto activo y quedan en el recorrido', function (): void {
    $store = activityStore();

    UserActivityTracker::recordStep(501, ['type' => 'action', 'label' => 'Guardó la afiliación', 'panel' => 'Negocios', 'page' => 'Afiliaciones', 'ms' => 230, 'status' => 200], activityNow());

    $event = $store->peekActivityEvents(5)[0];

    expect($store->activityMinutes(501, todayKey()))->toBe([minuteNow() => 'a'])
        ->and($event)->toMatchArray(['user_id' => 501, 'type' => 'action', 'label' => 'Guardó la afiliación', 'duration_ms' => 230, 'status' => 200])
        ->and(strlen($event['event_key']))->toBe(36);
});

it('apagado no registra nada', function (): void {
    $store = activityStore();
    config(['live-presence.activity.enabled' => false]);

    UserActivityTracker::recordPing(501, 'tabA', true, 0, 'heartbeat', [], activityNow());
    UserActivityTracker::recordStep(501, ['type' => 'page', 'label' => 'Inicio'], activityNow());

    expect($store->activityUsers(todayKey()))->toBe([])
        ->and($store->peekActivityEvents(5))->toBe([]);
});

it('lee los bitmaps de Redis con el bit 0 como el más alto del primer byte', function (): void {
    $method = new ReflectionMethod(RedisLivePresenceRepository::class, 'bitsSet');

    expect($method->invoke(null, "\x80\x01"))->toBe([0, 15])
        ->and($method->invoke(null, "\x00\x00\x40"))->toBe([17])
        ->and($method->invoke(null, false))->toBe([]);
});

it('resume el día: activo, inactivo, otra pestaña, horas y tramos de la barra', function (): void {
    $minutes = [480 => 'a', 481 => 'a', 482 => 'i', 483 => 'b', 540 => 'a'];
    $summary = UserActivityMinutes::summarize($minutes);
    $segments = UserActivityMinutes::segments($minutes);

    expect($summary)->toMatchArray(['online' => 5, 'active' => 3, 'idle' => 1, 'background' => 1, 'first' => 480, 'last' => 540])
        ->and($summary['hourly'][8])->toBe(2)
        ->and($summary['hourly'][9])->toBe(1)
        ->and(strlen($summary['states']))->toBe(1440)
        ->and(UserActivityMinutes::fromStates($summary['states']))->toBe($minutes)
        ->and(array_map(fn (array $segment): array => [$segment['from'], $segment['to'], $segment['state']->value], $segments))->toBe([[480, 481, 'a'], [482, 482, 'i'], [483, 483, 'b'], [540, 540, 'a']])
        ->and(UserActivityMinutes::usagePercent(3, 5))->toBe(60)
        ->and(UserActivityMinutes::usagePercent(0, 0))->toBe(0);
});

it('vuelca a la base el recorrido y el resumen del día, y repetirlo no duplica', function (): void {
    $store = activityStore();

    UserActivityTracker::recordStep(501, ['type' => 'page', 'label' => 'Afiliaciones', 'panel' => 'Negocios'], activityNow());
    UserActivityTracker::recordStep(501, ['type' => 'action', 'label' => 'Guardó', 'panel' => 'Negocios'], activityNow(30));
    UserActivityTracker::recordPing(501, 'tabA', true, 310_000, 'idle', [], activityNow(60));
    UserActivityTracker::recordPing(502, 'tabB', false, null, 'heartbeat', [], activityNow());

    $first = UserActivityFlusher::flush(UserActivityClock::now(), $store);
    $second = UserActivityFlusher::flush(UserActivityClock::now(), $store);

    $ana = UserActivityDay::query()->where('user_id', 501)->firstOrFail();

    expect($first)->toBe(['events' => 2, 'days' => 2])
        ->and($second['events'])->toBe(0)
        ->and(UserActivityEvent::query()->count())->toBe(2)
        ->and(UserActivityDay::query()->count())->toBe(2)
        ->and($ana->active_minutes)->toBe(1)
        ->and($ana->idle_minutes)->toBe(1)
        ->and($ana->page_views)->toBe(1)
        ->and($ana->actions)->toBe(1)
        ->and($ana->first_minute)->toBe(600)
        ->and($ana->activity_date->toDateString())->toBe('2026-10-08')
        ->and($store->peekActivityEvents(10))->toBe([]);
});

it('si la base falla, los eventos vuelven a la cola para el siguiente minuto', function (): void {
    $store = activityStore();
    UserActivityTracker::recordStep(501, ['type' => 'page', 'label' => 'Afiliaciones'], activityNow());

    Schema::drop('user_activity_events');

    expect(fn () => UserActivityFlusher::flushEvents($store))->toThrow(Illuminate\Database\QueryException::class)
        ->and($store->peekActivityEvents(10))->toHaveCount(1);
});

it('recién pasada la medianoche también cierra el día anterior', function (): void {
    $days = UserActivityFlusher::daysToFlush(CarbonImmutable::parse('2026-10-09 00:10:00', 'America/Caracas'));

    expect(array_map(fn (CarbonImmutable $day): string => $day->toDateString(), $days))->toBe(['2026-10-09', '2026-10-08'])
        ->and(UserActivityFlusher::daysToFlush(CarbonImmutable::parse('2026-10-09 09:00:00', 'America/Caracas')))->toHaveCount(1);
});

it('clasifica a cada persona: interno, agente, agencia', function (): void {
    $users = User::query()->get(UserActivityProfile::COLUMNS)->keyBy('id');

    expect(UserActivityProfile::classify($users[501]))->toBe(['type' => 'internal', 'label' => 'Interno', 'detail' => 'Negocios'])
        ->and(UserActivityProfile::classify($users[502])['type'])->toBe('agent')
        ->and(UserActivityProfile::classify($users[503]))->toMatchArray(['type' => 'agency', 'detail' => 'Agencia Master · TDG-200'])
        ->and(UserActivityProfile::classify(null)['type'])->toBe('other');
});

it('el reporte suma el rango por persona, ordena y filtra por tipo', function (): void {
    UserActivityDay::factory()->createMany([
        ['user_id' => 501, 'activity_date' => '2026-10-06', 'online_minutes' => 400, 'active_minutes' => 300, 'idle_minutes' => 80, 'background_minutes' => 20, 'actions' => 120, 'first_minute' => 480, 'last_minute' => 1020],
        ['user_id' => 501, 'activity_date' => '2026-10-07', 'online_minutes' => 400, 'active_minutes' => 340, 'idle_minutes' => 40, 'background_minutes' => 20, 'actions' => 140, 'first_minute' => 500, 'last_minute' => 1040],
        ['user_id' => 502, 'activity_date' => '2026-10-07', 'online_minutes' => 480, 'active_minutes' => 60, 'idle_minutes' => 400, 'background_minutes' => 20, 'actions' => 10, 'first_minute' => 540, 'last_minute' => 1020],
        ['user_id' => 501, 'activity_date' => '2026-09-01', 'online_minutes' => 999, 'active_minutes' => 999],
    ]);

    [$from, $to] = UserActivityReport::range('2026-10-05', '2026-10-08');
    $report = UserActivityReport::summary($from, $to, ['sort' => 'active']);
    $ana = $report['rows'][0];

    expect(array_column($report['rows'], 'name'))->toBe(['Ana Pérez', 'Luis Agente'])
        ->and($ana)->toMatchArray(['days' => 2, 'online' => 800, 'active' => 640, 'idle' => 120, 'usage' => 80, 'avg_active' => 320, 'actions' => 260, 'avg_first' => 490, 'avg_last' => 1030, 'type' => 'internal'])
        ->and($report['rows'][1]['usage'])->toBe(13)
        ->and($report['totals'])->toMatchArray(['users' => 2, 'active' => 700, 'online' => 1280, 'usage' => 55])
        ->and(array_column(UserActivityReport::summary($from, $to, ['sort' => 'usage', 'direction' => 'asc'])['rows'], 'name'))->toBe(['Luis Agente', 'Ana Pérez'])
        ->and(array_column(UserActivityReport::summary($from, $to, ['type' => 'agent'])['rows'], 'name'))->toBe(['Luis Agente'])
        ->and(UserActivityReport::summary($from, $to, ['search' => 'nadie'])['rows'])->toBe([]);
});

it('el rango se ordena solo, no pasa de hoy ni de un año', function (): void {
    [$from, $to] = UserActivityReport::range('2026-10-08', '2026-10-01');
    expect([$from->toDateString(), $to->toDateString()])->toBe(['2026-10-01', '2026-10-08']);

    [, $to] = UserActivityReport::range('2026-10-01', '2027-01-01');
    expect($to->toDateString())->toBe('2026-10-08');

    [$from] = UserActivityReport::range('2020-01-01', '2026-10-08');
    expect($from->diffInDays(CarbonImmutable::parse('2026-10-08')))->toBeLessThan(366.0);

    [$from, $to] = UserActivityReport::range('no-es-fecha', null);
    expect([$from->toDateString(), $to->toDateString()])->toBe(['2026-10-05', '2026-10-08']);
});

it('el día de hoy junta lo ya guardado con lo que aún no se volcó', function (): void {
    $store = activityStore();

    UserActivityTracker::recordStep(501, ['type' => 'page', 'label' => 'Ya guardada'], activityNow(-120));
    UserActivityFlusher::flushEvents($store);
    UserActivityTracker::recordStep(501, ['type' => 'action', 'label' => 'Aún en la cola'], activityNow());
    UserActivityTracker::recordStep(502, ['type' => 'action', 'label' => 'De otra persona'], activityNow());

    $day = UserActivityReport::day(501, UserActivityClock::now());

    expect($day['is_today'])->toBeTrue()
        ->and(array_column($day['events'], 'label'))->toBe(['Aún en la cola', 'Ya guardada'])
        ->and($day['summary']['active'])->toBe(2)
        ->and($day['segments'])->not->toBe([]);
});

it('pasados los 90 días queda el resumen sin la barra minuto a minuto', function (): void {
    UserActivityDay::factory()->create(['user_id' => 501, 'activity_date' => '2026-05-04', 'online_minutes' => 300, 'active_minutes' => 200, 'minute_states' => null, 'first_minute' => 480]);

    $day = UserActivityReport::day(501, CarbonImmutable::parse('2026-05-04', 'America/Caracas'));

    expect($day['detail_available'])->toBeFalse()
        ->and($day['segments'])->toBe([])
        ->and($day['summary'])->toMatchArray(['active' => 200, 'online' => 300, 'usage' => 67, 'first' => 480]);
});

it('la retención borra el recorrido de más de 90 días y los resúmenes de más de 12 meses', function (): void {
    UserActivityEvent::factory()->create(['user_id' => 501, 'occurred_at' => '2026-07-01 09:00:00']);
    UserActivityEvent::factory()->create(['user_id' => 501, 'occurred_at' => '2026-07-20 09:00:00']);
    UserActivityDay::factory()->create(['user_id' => 501, 'activity_date' => '2026-07-01', 'minute_states' => str_repeat('a', 1440)]);
    UserActivityDay::factory()->create(['user_id' => 501, 'activity_date' => '2026-07-20', 'minute_states' => str_repeat('a', 1440)]);
    UserActivityDay::factory()->create(['user_id' => 501, 'activity_date' => '2025-09-30']);

    $result = UserActivityRetention::purge();

    expect($result)->toBe(['events' => 1, 'bars' => 1, 'days' => 1])
        ->and(UserActivityEvent::query()->pluck('occurred_at')->map->format('Y-m-d')->all())->toBe(['2026-07-20'])
        ->and(UserActivityDay::query()->whereDate('activity_date', '2026-07-20')->value('minute_states'))->not->toBeNull()
        ->and(UserActivityDay::query()->whereDate('activity_date', '2026-07-01')->value('minute_states'))->toBeNull()
        ->and(UserActivityDay::query()->count())->toBe(2);
});

it('el tablero en vivo junta conectados y quienes ya trabajaron hoy, ordenados por estado', function (): void {
    $store = activityStore();

    UserActivityTracker::recordPing(502, 'tabB', true, 400_000, 'idle', [], activityNow());
    UserActivityTracker::recordPing(501, 'tabA', true, 0, 'heartbeat', ['page' => 'Cotizaciones', 'panel' => 'Negocios'], activityNow());
    UserActivityTracker::recordPing(503, 'tabC', true, 0, 'heartbeat', [], activityNow(-3600));

    $board = UserActivityLiveBoard::build(['state' => 'all'], activityNow(), $store);

    expect($board['available'])->toBeTrue()
        ->and(array_column($board['rows'], 'name'))->toBe(['Ana Pérez', 'Luis Agente', 'Agencia Sol'])
        ->and($board['rows'][0]['state'])->toBe(UserActivityState::Active)
        ->and($board['rows'][1]['state'])->toBe(UserActivityState::Idle)
        ->and($board['rows'][2]['state'])->toBe(UserActivityState::Offline)
        ->and($board['kpis'])->toMatchArray(['active' => 1, 'idle' => 1, 'offline' => 1, 'connected' => 2, 'today' => 3])
        ->and(array_column(UserActivityLiveBoard::build(['state' => 'idle'], activityNow(), $store)['rows'], 'name'))->toBe(['Luis Agente'])
        ->and(array_column(UserActivityLiveBoard::build(['state' => 'all', 'type' => 'agency'], activityNow(), $store)['rows'], 'name'))->toBe(['Agencia Sol'])
        ->and(UserActivityLiveBoard::build(['state' => 'all', 'search' => 'cotiza'], activityNow(), $store)['rows'][0]['name'])->toBe('Ana Pérez');
});

it('solo entra quien tiene el permiso actividad-usuarios (SUPERADMIN siempre)', function (): void {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(UserActivityMonitor::class))->toBe(['actividad-usuarios'])
        ->and(DepartmentNavigationPermissionRegistry::isSuperAdminOnly(UserActivityMonitor::class))->toBeFalse();

    actingAsActivityUser(['NEGOCIOS'], 901);
    expect(UserActivityMonitor::canAccess())->toBeFalse();

    actingAsActivityUser(['SUPERADMIN'], 902);
    expect(UserActivityMonitor::canAccess())->toBeTrue();
});

it('la pantalla muestra el en vivo, el reporte y el detalle de una persona', function (): void {
    Filament::setCurrentPanel('business');
    activityStore();
    actingAsActivityUser(['SUPERADMIN']);

    UserActivityTracker::recordPing(501, 'tabA', true, 0, 'heartbeat', ['page' => 'Cotizaciones', 'panel' => 'Negocios'], activityNow());
    UserActivityTracker::recordPing(502, 'tabB', true, 400_000, 'idle', [], activityNow());
    UserActivityTracker::recordStep(501, ['type' => 'action', 'label' => 'Guardó la cotización'], activityNow());
    UserActivityDay::factory()->create(['user_id' => 501, 'activity_date' => '2026-10-07', 'online_minutes' => 400, 'active_minutes' => 300, 'idle_minutes' => 100, 'background_minutes' => 0]);

    Livewire::test(UserActivityMonitor::class)
        ->assertOk()
        ->assertSee('Activos ahora')
        ->assertSee('Ana Pérez')
        ->assertSee('Luis Agente')
        ->assertSee('Sin tocarlo')
        ->call('filterState', 'active')
        ->assertSee('Ana Pérez')
        ->assertDontSee('Luis Agente')
        ->call('selectUser', 501)
        ->assertSee('Su día minuto a minuto')
        ->assertSee('Guardó la cotización')
        ->call('closeDetail')
        ->call('setTab', 'report')
        ->call('preset', 'week')
        ->assertSet('from', '2026-10-05')
        ->assertSee('Abierto sin usar')
        ->assertSee('75%')
        ->call('selectUser', 501, '2026-10-07')
        ->assertSee('¿A qué horas usa el sistema?')
        ->call('shiftDay', 1)
        ->assertSet('selectedDate', '2026-10-08')
        ->call('shiftDay', 1)
        ->assertSet('selectedDate', '2026-10-08');
});

it('la descarga a Excel respeta el permiso y trae una fila por persona', function (): void {
    UserActivityDay::factory()->create(['user_id' => 501, 'activity_date' => '2026-10-07', 'online_minutes' => 400, 'active_minutes' => 300]);

    actingAsActivityUser(['NEGOCIOS'], 903);
    $this->get(route('business.user-activity.export', ['desde' => '2026-10-01', 'hasta' => '2026-10-08']))->assertForbidden();

    actingAsActivityUser(['SUPERADMIN'], 904);
    $response = $this->get(route('business.user-activity.export', ['desde' => '2026-10-01', 'hasta' => '2026-10-08']));
    $response->assertOk();
    $csv = $response->streamedContent();

    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('"Ana Pérez";ana@tudrencasa.com;Interno;Negocios;1;400;300')
        ->and($csv)->toContain('TOTAL');
});

it('el latido acepta pestaña e inactividad y rechaza una pestaña con caracteres extraños', function (): void {
    activityStore();
    $this->withoutMiddleware(Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    actingAsActivityUser(['NEGOCIOS'], 501);

    $this->postJson('/lp/s', ['reason' => 'idle', 'tab' => 'abc-123', 'idle' => 310000, 'visible' => true, 'path' => '/business'])->assertNoContent();
    $this->postJson('/lp/s', ['reason' => 'activity', 'tab' => '<script>', 'idle' => 0])->assertUnprocessable();
    $this->postJson('/lp/s', ['reason' => 'teclado', 'idle' => 0])->assertUnprocessable();

    expect(LivePresenceStore::repository()->activityMinutes(501, todayKey()))->toBe([minuteNow() => 'i']);
});

it('el script del navegador mide la interacción sin leer lo que se escribe', function (): void {
    actingAsActivityUser(['NEGOCIOS'], 905);

    $html = view('live-presence.beacon')->render();

    expect($html)
        ->toContain("['keydown', 'pointerdown', 'wheel', 'touchstart', 'scroll']")
        ->toContain('idle: Math.max(0, Date.now() - lastInteraction)')
        ->toContain('tab: tabId')
        ->toContain("ping('idle')")
        ->toContain("ping('activity')")
        ->not->toContain('event.key')
        ->not->toContain('.value');
});

it('la barra del día muestra la escala de horas alineada en la tabla y en el detalle', function (): void {
    Filament::setCurrentPanel('business');
    activityStore();
    actingAsActivityUser(['SUPERADMIN']);

    UserActivityTracker::recordPing(501, 'tabA', true, 0, 'heartbeat', ['page' => 'Cotizaciones', 'panel' => 'Negocios'], activityNow());

    $componente = Livewire::test(UserActivityMonitor::class)
        ->assertOk()
        ->assertSeeHtml('<span class="uam-hours" aria-hidden="true">')
        ->assertSeeHtml('<span class="first" style="left: 0%;">12a</span>')
        ->assertSeeHtml('<span class="" style="left: 25%;">6a</span>')
        ->assertSeeHtml('<span class="" style="left: 50%;">12p</span>')
        ->assertSeeHtml('<span class="" style="left: 75%;">6p</span>')
        ->assertSeeHtml('<span class="last" style="left: 100%;">12a</span>');

    expect(substr_count($componente->html(), 'class="uam-hours"'))->toBe(1);

    $componente->call('selectUser', 501)
        ->assertSeeHtml('<span class="uam-hours big" aria-hidden="true">');
});
