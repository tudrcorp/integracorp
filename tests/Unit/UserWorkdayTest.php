<?php

declare(strict_types=1);

use App\Filament\Business\Pages\UserActivityMonitor;
use App\Livewire\WorkBreakToggle;
use App\Models\User;
use App\Models\UserActivityDay;
use App\Models\UserWorkAbsence;
use App\Models\UserWorkBreak;
use App\Models\UserWorkMeeting;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\LivePresence\CacheLivePresenceRepository;
use App\Support\LivePresence\LivePresenceStore;
use App\Support\UserActivity\UserActivityClock;
use App\Support\UserActivity\UserActivityFlusher;
use App\Support\UserActivity\UserActivityLiveBoard;
use App\Support\UserActivity\UserActivityReport;
use App\Support\UserActivity\UserActivityTracker;
use App\Support\UserActivity\Workday\WorkAbsenceType;
use App\Support\UserActivity\Workday\WorkBreakException;
use App\Support\UserActivity\Workday\WorkBreaks;
use App\Support\UserActivity\Workday\WorkDayClassifier;
use App\Support\UserActivity\Workday\WorkdayConfig;
use App\Support\UserActivity\Workday\WorkDayKind;
use App\Support\UserActivity\Workday\WorkDayPlanner;
use App\Support\UserActivity\Workday\WorkdaySubjects;
use App\Support\UserActivity\Workday\WorkGuardSource;
use App\Support\UserActivity\Workday\WorkHolidays;
use App\Support\UserActivity\Workday\WorkMeetings;
use App\Support\UserActivity\Workday\WorkMinuteKind;
use App\Support\UserActivity\Workday\WorkScheduleType;
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
 * Jornada laboral en Actividad de usuarios. Igual que UserActivityTest: la
 * actividad en vivo va a la caché `array` y lo que se guarda a una conexión
 * sqlite `:memory:` propia; este test nunca toca la base real.
 */
function workdaySchema(): void
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

    Schema::create('rrhh_colaboradors', function (Blueprint $table): void {
        $table->id();
        $table->string('fullName')->nullable();
        $table->string('emailCorporativo')->nullable();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->timestamps();
    });

    Schema::create('tdg_calendar_days', function (Blueprint $table): void {
        $table->id();
        $table->date('calendar_date');
        $table->unsignedBigInteger('updated_by_user_id')->nullable();
        $table->timestamps();
    });

    Schema::create('tdg_calendar_guard_assignments', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('tdg_calendar_day_id');
        $table->string('guard_shift');
        $table->unsignedBigInteger('rrhh_colaborador_id')->nullable();
        $table->timestamps();
    });

    Schema::create('operation_on_call_users', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('rrhh_colaborador_id')->nullable();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('phone')->nullable();
        $table->string('hrs_init')->nullable();
        $table->string('hrs_end')->nullable();
        $table->string('date_OnCall')->nullable();
        $table->string('status')->nullable();
        $table->unsignedBigInteger('created_by')->nullable();
        $table->unsignedBigInteger('updated_by')->nullable();
        $table->timestamps();
    });

    /** La migración real, dos veces: es idempotente. */
    $migration = require base_path('database/migrations/2026_10_16_120000_create_user_workday_tables.php');
    $migration->up();
    $migration->up();

    $meetings = require base_path('database/migrations/2026_10_16_200000_create_user_work_meetings_table.php');
    $meetings->up();
    $meetings->up();

    $base = ['departament' => null, 'is_agent' => false, 'is_agency' => false, 'agency_type' => null, 'code_agency' => null, 'is_doctor' => false, 'status' => 'ACTIVO'];

    DB::table('users')->insert([
        [...$base, 'id' => 601, 'name' => 'Ana Interna', 'email' => 'ana@tudrencasa.com', 'departament' => '["NEGOCIOS"]'],
        [...$base, 'id' => 602, 'name' => 'Dra. Externa', 'email' => 'dra@gmail.com', 'is_doctor' => true],
        [...$base, 'id' => 603, 'name' => 'Agente Casa', 'email' => 'agente@tudrencasa.com', 'is_agent' => true, 'code_agency' => 'TDG-100'],
        [...$base, 'id' => 604, 'name' => 'Nora Nocturna', 'email' => 'nora@tudrencasa.com', 'departament' => '["OPERACIONES"]'],
        [...$base, 'id' => 605, 'name' => 'Inés Inactiva', 'email' => 'ines@tudrencasa.com', 'departament' => '["NEGOCIOS"]', 'status' => 'INACTIVO'],
    ]);
}

beforeEach(function (): void {
    config([
        'live-presence.enabled' => true,
        'live-presence.store' => 'cache',
        'live-presence.cache_store' => 'array',
        'live-presence.activity.enabled' => true,
        'live-presence.activity.idle_after_seconds' => 300,
        'live-presence.activity.workday.enabled' => true,
        'live-presence.activity.workday.since' => '2026-10-01',
        'live-presence.activity.workday.extra_holidays' => [],
        'live-presence.geoip.database' => '/no/existe.mmdb',
        'cache.default' => 'array',
        'session.driver' => 'array',
        'database.connections.workday_testing' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
    ]);

    $this->previousConnection = config('database.default');
    config()->set('database.default', 'workday_testing');
    DB::purge('workday_testing');
    DB::setDefaultConnection('workday_testing');

    expect(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(DB::connection()->getDatabaseName())->toBe(':memory:');

    workdaySchema();
    Cache::store('array')->flush();
    LivePresenceStore::swap(null);
    WorkdayConfig::flushSchemaCache();

    /** Jueves 15 de octubre de 2026, 10:00 en Caracas. */
    $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00', 'America/Caracas'));
});

afterEach(function (): void {
    LivePresenceStore::swap(null);
    WorkdayConfig::flushSchemaCache();
    DB::purge('workday_testing');
    config()->set('database.default', $this->previousConnection);
    DB::setDefaultConnection($this->previousConnection);
});

function wdStore(): CacheLivePresenceRepository
{
    $store = new CacheLivePresenceRepository(Cache::store('array'), 300, 50, 3600, 100);
    LivePresenceStore::swap($store);

    return $store;
}

function wdUser(int $id): User
{
    return User::query()->findOrFail($id, WorkdaySubjects::columns());
}

function wdAt(string $moment): CarbonImmutable
{
    return CarbonImmutable::parse($moment, 'America/Caracas');
}

/**
 * Minutos del día con un estado por tramo: [[desde, hasta (excluido), código], …].
 *
 * @param  list<array{0: int, 1: int, 2: string}>  $ranges
 * @return array<int, string>
 */
function wdMinutes(array $ranges): array
{
    $minutes = [];

    foreach ($ranges as [$from, $to, $code]) {
        for ($minute = $from; $minute < $to; $minute++) {
            $minutes[$minute] = $code;
        }
    }

    return $minutes;
}

function wdGuard(string $date, string $shift, int $colaboradorId): void
{
    $dayId = DB::table('tdg_calendar_days')->where('calendar_date', $date)->value('id')
        ?? DB::table('tdg_calendar_days')->insertGetId(['calendar_date' => $date]);

    DB::table('tdg_calendar_guard_assignments')->insert(['tdg_calendar_day_id' => $dayId, 'guard_shift' => $shift, 'rrhh_colaborador_id' => $colaboradorId]);
}

function wdActingAs(array $departments, int $id = 900): User
{
    $user = User::factory()->make(['name' => 'Supervisor', 'email' => 'super'.$id.'@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => $departments]);
    $user->id = $id;
    $user->setRelation('permissions', new EloquentCollection);
    test()->actingAs($user);

    return $user;
}

it('calcula los feriados federales de EE. UU. con su día observado', function (): void {
    $holidays = WorkHolidays::forYear(2026);

    expect($holidays)->toHaveKey('2026-10-12')
        ->and($holidays['2026-10-12'])->toContain('Columbus')
        ->and($holidays)->toHaveKey('2026-07-03')
        ->and($holidays)->not->toHaveKey('2026-07-04')
        ->and($holidays)->toHaveKey('2026-11-26')
        ->and($holidays)->toHaveKey('2026-01-19')
        ->and($holidays)->toHaveKey('2026-05-25')
        ->and($holidays)->toHaveCount(11)
        ->and(WorkHolidays::nameFor(wdAt('2027-12-31')))->toBe('Año Nuevo')
        ->and(WorkHolidays::nameFor(wdAt('2026-10-15')))->toBeNull();

    config(['live-presence.activity.workday.extra_holidays' => ['2026-10-15']]);
    expect(WorkHolidays::nameFor(wdAt('2026-10-15')))->toBe('Feriado');

    config(['live-presence.activity.workday.us_federal_holidays' => false, 'live-presence.activity.workday.extra_holidays' => []]);
    expect(WorkHolidays::nameFor(wdAt('2026-10-12')))->toBeNull();
});

it('mide a los internos activos y a todos los médicos, no a agentes ni inactivos', function (): void {
    expect(WorkdaySubjects::all()->keys()->sort()->values()->all())->toBe([601, 602, 604])
        ->and(WorkdaySubjects::isSubject(wdUser(603)))->toBeFalse()
        ->and(WorkdaySubjects::isSubject(wdUser(605)))->toBeFalse()
        ->and(WorkdaySubjects::isSubject(null))->toBeFalse();
});

it('arma el día: laboral, fin de semana, feriado, ausencia, exenta y sin turno', function (): void {
    UserWorkAbsence::factory()->create(['user_id' => 601, 'type' => WorkAbsenceType::Vacation, 'starts_on' => '2026-10-20', 'ends_on' => '2026-10-21', 'reason' => 'Período 2026']);
    DB::table('users')->where('id', 602)->update(['work_schedule' => WorkScheduleType::Exempt->value]);
    DB::table('users')->where('id', 604)->update(['work_schedule' => WorkScheduleType::Guards->value]);

    $plans = WorkDayPlanner::plans(WorkdaySubjects::all(), wdAt('2026-10-12'), wdAt('2026-10-21'));

    expect($plans[601]['2026-10-15']->kind)->toBe(WorkDayKind::Workday)
        ->and($plans[601]['2026-10-15']->workStart)->toBe(480)
        ->and($plans[601]['2026-10-15']->workEnd)->toBe(1020)
        ->and($plans[601]['2026-10-15']->lunchAllowed())->toBeTrue()
        ->and($plans[601]['2026-10-17']->kind)->toBe(WorkDayKind::Weekend)
        ->and($plans[601]['2026-10-12']->kind)->toBe(WorkDayKind::Holiday)
        ->and($plans[601]['2026-10-12']->label())->toContain('Columbus')
        ->and($plans[601]['2026-10-20']->kind)->toBe(WorkDayKind::Absence)
        ->and($plans[601]['2026-10-20']->label())->toBe('Ausencia justificada: Vacaciones · Período 2026')
        ->and($plans[601]['2026-10-20']->hasJornada())->toBeFalse()
        ->and($plans[602]['2026-10-15']->kind)->toBe(WorkDayKind::Exempt)
        ->and($plans[604]['2026-10-15']->kind)->toBe(WorkDayKind::NoShift);
});

it('una guardia nocturna cuenta para el día en que empieza y el día siguiente es de descanso', function (): void {
    $colaborador = DB::table('rrhh_colaboradors')->insertGetId(['fullName' => 'Nora', 'emailCorporativo' => 'NORA@tudrencasa.com', 'user_id' => null]);
    wdGuard('2026-10-14', 'nocturna', $colaborador);

    $plans = WorkDayPlanner::plans(WorkdaySubjects::all(), wdAt('2026-10-14'), wdAt('2026-10-15'));
    $wednesday = $plans[604]['2026-10-14'];
    $thursday = $plans[604]['2026-10-15'];

    expect($wednesday->kind)->toBe(WorkDayKind::Workday)
        ->and($wednesday->guards)->toBe([['from' => 1020, 'to' => 1440, 'owner' => '2026-10-14', 'label' => 'Guardia 3.0 Nocturna', 'overtime' => false]])
        ->and($wednesday->spill)->toBe([['from' => 0, 'to' => 480, 'label' => 'Guardia 3.0 Nocturna', 'overtime' => false]])
        ->and($thursday->kind)->toBe(WorkDayKind::GuardRest)
        ->and($thursday->guards[0])->toMatchArray(['from' => 0, 'to' => 480, 'owner' => '2026-10-14'])
        ->and($plans[601]['2026-10-15']->kind)->toBe(WorkDayKind::Workday);

    /** Miércoles: trabajó de 5 p. m. a medianoche y de 12 a 6 a. m.; de 6 a 8 a. m. no estuvo. */
    $wednesdayResult = WorkDayClassifier::classify($wednesday, wdMinutes([[480, 1020, 'a'], [1020, 1440, 'i']]), ['start' => 720, 'end' => 780], 1440, wdMinutes([[0, 360, 'a']]), 1440);

    expect($wednesdayResult['summary'])->toMatchArray(['guard' => 420 + 480, 'guard_present' => 420 + 360, 'guard_active' => 360, 'overtime' => 0, 'guard_presence' => 87]);

    /** Jueves: la cola de la guardia se pinta, pero no cuenta para el jueves. */
    $thursdayResult = WorkDayClassifier::classify($thursday, wdMinutes([[0, 360, 'a']]), null, 600);

    expect($thursdayResult['summary'])->toMatchArray(['guard' => 0, 'base' => 0, 'overtime' => 0, 'usage' => null])
        ->and($thursdayResult['codes'][100])->toBe(WorkMinuteKind::GuardActive->value)
        ->and($thursdayResult['codes'][400])->toBe(WorkMinuteKind::GuardAbsent->value)
        ->and($thursdayResult['segments'][0]['note'])->toContain('Guardia 3.0 Nocturna del');
});

it('una guardia en fin de semana cuenta como horas extra y no penaliza la ausencia', function (): void {
    $colaborador = DB::table('rrhh_colaboradors')->insertGetId(['fullName' => 'Nora', 'emailCorporativo' => 'otra@x.com', 'user_id' => 604]);
    wdGuard('2026-10-17', 'proveedores', $colaborador);

    $saturday = WorkDayPlanner::plans(WorkdaySubjects::all(), wdAt('2026-10-17'), wdAt('2026-10-17'))[604]['2026-10-17'];
    $result = WorkDayClassifier::classify($saturday, wdMinutes([[480, 600, 'a'], [600, 660, 'i']]), null, 1440);

    expect($saturday->kind)->toBe(WorkDayKind::Weekend)
        ->and($saturday->guards[0]['overtime'])->toBeTrue()
        ->and($result['summary'])->toMatchArray(['overtime' => 120, 'guard' => 0, 'base' => 0, 'work_absent' => 0])
        ->and($result['codes'][700])->toBe(WorkMinuteKind::Free->value)
        ->and($result['bands'][0]['type'])->toBe('guard-extra');
});

it('quien trabaja por guardias tiene jornada solo el día de su turno diurno', function (): void {
    DB::table('users')->where('id', 604)->update(['work_schedule' => WorkScheduleType::Guards->value]);
    $colaborador = DB::table('rrhh_colaboradors')->insertGetId(['fullName' => 'Nora', 'emailCorporativo' => null, 'user_id' => 604]);
    wdGuard('2026-10-15', 'ils_capitado', $colaborador);

    $plans = WorkDayPlanner::plans(WorkdaySubjects::all(), wdAt('2026-10-15'), wdAt('2026-10-16'));

    expect($plans[604]['2026-10-15']->kind)->toBe(WorkDayKind::Workday)
        ->and($plans[604]['2026-10-16']->kind)->toBe(WorkDayKind::NoShift);
});

it('lee las guardias de Operaciones con su franja y el correo de la guardia', function (): void {
    DB::table('operation_on_call_users')->insert([
        ['email' => 'dra@gmail.com', 'hrs_init' => '20:00', 'hrs_end' => '02:00', 'date_OnCall' => '15/10/2026', 'status' => 'PROGRAMADA'],
        ['email' => 'dra@gmail.com', 'hrs_init' => '08:00', 'hrs_end' => '12:00', 'date_OnCall' => '16/10/2026', 'status' => 'ANULADA'],
        ['email' => 'dra@gmail.com', 'hrs_init' => '', 'hrs_end' => '', 'date_OnCall' => '16/10/2026', 'status' => 'PROGRAMADA'],
    ]);

    $windows = WorkGuardSource::windows(WorkdaySubjects::all(), wdAt('2026-10-15'), wdAt('2026-10-16'));

    expect($windows[602])->toBe(['2026-10-15' => [['from' => 1200, 'to' => 1560, 'label' => 'Guardia de Operaciones']]])
        ->and(WorkGuardSource::parseOperationDate('15/10/2026'))->toBe('2026-10-15')
        ->and(WorkGuardSource::parseOperationDate('2026-10-15'))->toBe('2026-10-15')
        ->and(WorkGuardSource::parseOperationDate('31/02/2026'))->toBeNull();
});

it('uso de jornada: almuerzo declarado, exceso, inactividad, ausencia y horas extra', function (): void {
    $plan = WorkDayPlanner::forDay(wdUser(601), wdAt('2026-10-15'));
    $minutes = wdMinutes([
        [480, 600, 'a'],   // 8:00–10:00 activa
        [600, 630, 'i'],   // 10:00–10:30 abierto sin tocarlo
        [630, 720, 'a'],   // 10:30–12:00 activa
        [787, 960, 'a'],   // 1:07–4:00 activa (salió a almorzar 12:00–1:07)
        [1080, 1140, 'a'], // 6:00–7:00 p. m.: horas extra
        [1140, 1200, 'b'], // 7:00–8:00 p. m.: abierto sin usar, no es extra
    ]);

    $result = WorkDayClassifier::classify($plan, $minutes, ['start' => 720, 'end' => 787, 'reason' => 'manual'], 1440);
    $summary = $result['summary'];

    expect($summary)->toMatchArray([
        'window' => 540, 'lunch' => 60, 'lunch_excess' => 7, 'base' => 480,
        'work_active' => 383, 'work_idle' => 30, 'work_absent' => 60, 'overtime' => 60, 'usage' => 80,
        'lunch_declared' => true, 'lunch_open' => false,
    ])
        ->and($result['codes'][720])->toBe('L')
        ->and($result['codes'][781])->toBe('E')
        ->and($result['codes'][1000])->toBe('X')
        ->and($result['codes'][1100])->toBe('O')
        ->and($result['codes'][1150])->toBe('B')
        ->and(array_map(fn (array $segment): string => $segment['kind']->value, $result['segments']))->toBe(['W', 'I', 'W', 'L', 'E', 'W', 'X', 'O', 'B'])
        ->and($result['segments'][4]['note'])->toBe('7 min por encima de la hora de almuerzo')
        ->and($result['bands'][0])->toMatchArray(['from' => 480, 'to' => 1020, 'type' => 'work']);
});

it('sin almuerzo declarado esa hora es jornada y cuenta como ausencia si no trabajó', function (): void {
    $plan = WorkDayPlanner::forDay(wdUser(601), wdAt('2026-10-15'));
    $result = WorkDayClassifier::classify($plan, wdMinutes([[480, 720, 'a'], [780, 1020, 'a']]), null, 1440);

    expect($result['summary'])->toMatchArray(['base' => 540, 'work_active' => 480, 'work_absent' => 60, 'usage' => 89, 'lunch_declared' => false]);
});

it('el uso es progresivo: solo cuenta la jornada que ya pasó', function (): void {
    $plan = WorkDayPlanner::forDay(wdUser(601), wdAt('2026-10-15'));
    $result = WorkDayClassifier::classify($plan, wdMinutes([[480, 570, 'a']]), null, 600);

    expect($result['summary'])->toMatchArray(['base' => 120, 'work_active' => 90, 'work_absent' => 30, 'usage' => 75])
        ->and($result['codes'][700])->toBe('.');

    $early = WorkDayClassifier::classify($plan, [], null, 420);
    expect($early['summary'])->toMatchArray(['base' => 0, 'usage' => null, 'work_absent' => 0]);
});

it('un almuerzo abierto cuenta hasta ahora y se pasa a exceso al cumplir la hora', function (): void {
    $plan = WorkDayPlanner::forDay(wdUser(601), wdAt('2026-10-15'));
    $result = WorkDayClassifier::classify($plan, wdMinutes([[480, 720, 'a']]), ['start' => 720, 'end' => null], 800);

    expect($result['summary'])->toMatchArray(['lunch' => 60, 'lunch_excess' => 20, 'lunch_open' => true, 'base' => 260, 'work_active' => 240]);
});

it('la jornada exenta mide el uso sin base ni horas extra', function (): void {
    DB::table('users')->where('id', 601)->update(['work_schedule' => WorkScheduleType::Exempt->value]);
    $plan = WorkDayPlanner::forDay(wdUser(601), wdAt('2026-10-15'));
    $result = WorkDayClassifier::classify($plan, wdMinutes([[480, 600, 'a'], [1200, 1260, 'a']]), null, 1440);

    expect($result['summary'])->toMatchArray(['base' => 0, 'usage' => null, 'overtime' => 0, 'work_active' => 180, 'work_absent' => 0]);
});

it('el almuerzo se declara a cualquier hora de la jornada, una vez por día, y repetir el clic no duplica', function (): void {
    $ana = wdUser(601);

    expect(fn () => WorkBreaks::start($ana, 'business', wdAt('2026-10-15 07:50')))->toThrow(WorkBreakException::class, 'El almuerzo se declara dentro de tu jornada, de 8:00 a. m. a 5:00 p. m.')
        ->and(fn () => WorkBreaks::start($ana, 'business', wdAt('2026-10-15 17:00')))->toThrow(WorkBreakException::class, 'dentro de tu jornada')
        ->and(WorkBreaks::status($ana, wdAt('2026-10-15 15:40'))['state'])->toBe('ready');

    $first = WorkBreaks::start($ana, 'business', wdAt('2026-10-15 12:00'));
    $again = WorkBreaks::start($ana, 'business', wdAt('2026-10-15 12:01'));

    expect($again->id)->toBe($first->id)
        ->and(UserWorkBreak::query()->count())->toBe(1)
        ->and(WorkBreaks::status($ana, wdAt('2026-10-15 12:30')))->toMatchArray(['state' => 'on', 'minutes' => 30, 'excess' => 0]);

    $ended = WorkBreaks::end($ana, UserWorkBreak::END_MANUAL, wdAt('2026-10-15 13:10'));

    expect($ended->ended_at->format('H:i'))->toBe('13:10')
        ->and(WorkBreaks::end($ana, UserWorkBreak::END_MANUAL, wdAt('2026-10-15 13:20')))->toBeNull()
        ->and(UserWorkBreak::query()->first()->ended_at->format('H:i'))->toBe('13:10')
        ->and(WorkBreaks::status($ana, wdAt('2026-10-15 13:30')))->toMatchArray(['state' => 'done', 'minutes' => 70, 'excess' => 10])
        ->and(DB::table('logs')->pluck('action')->all())->toBe(['AUDIT_WORK_BREAK_STARTED', 'AUDIT_WORK_BREAK_ENDED']);

    expect(fn () => WorkBreaks::start($ana, 'business', wdAt('2026-10-15 13:40')))->toThrow(WorkBreakException::class, 'Ya tomaste tu almuerzo hoy');
});

it('no deja declarar almuerzo sin jornada: fin de semana, agente o ausencia', function (): void {
    expect(fn () => WorkBreaks::start(wdUser(601), null, wdAt('2026-10-17 12:00')))->toThrow(WorkBreakException::class, 'Hoy no tienes jornada laboral (fin de semana)')
        ->and(fn () => WorkBreaks::start(wdUser(603), null, wdAt('2026-10-15 12:00')))->toThrow(WorkBreakException::class, 'no tiene jornada laboral')
        ->and(WorkBreaks::status(wdUser(603), wdAt('2026-10-15 12:00'))['state'])->toBe('hidden')
        ->and(WorkBreaks::status(wdUser(601), wdAt('2026-10-15 07:30'))['state'])->toBe('early')
        ->and(WorkBreaks::status(wdUser(601), wdAt('2026-10-15 09:00'))['state'])->toBe('ready')
        ->and(WorkBreaks::status(wdUser(601), wdAt('2026-10-15 17:10'))['state'])->toBe('missed');

    UserWorkAbsence::factory()->create(['user_id' => 602, 'type' => WorkAbsenceType::MedicalLeave, 'starts_on' => '2026-10-15', 'ends_on' => '2026-10-15']);
    expect(fn () => WorkBreaks::start(wdUser(602), null, wdAt('2026-10-15 12:00')))->toThrow(WorkBreakException::class, 'ausencia justificada');
});

it('si no marca el regreso, el almuerzo se cierra solo al terminar la jornada', function (): void {
    WorkBreaks::start(wdUser(601), null, wdAt('2026-10-15 12:00'));

    expect(WorkBreaks::closeExpired(wdAt('2026-10-15 16:59')))->toBe(0)
        ->and(WorkBreaks::closeExpired(wdAt('2026-10-15 17:05')))->toBe(1)
        ->and(WorkBreaks::closeExpired(wdAt('2026-10-15 17:06')))->toBe(0);

    $break = UserWorkBreak::query()->first();
    $lunch = WorkBreaks::asMinutes($break);
    $result = WorkDayClassifier::classify(WorkDayPlanner::forDay(wdUser(601), wdAt('2026-10-15')), [], $lunch, 1440);

    expect($break->ended_at->format('H:i'))->toBe('17:00')
        ->and($break->end_reason)->toBe(UserWorkBreak::END_AUTO)
        ->and($result['summary'])->toMatchArray(['lunch' => 60, 'lunch_excess' => 240, 'lunch_reason' => 'auto'])
        ->and(end($result['segments'])['note'])->toContain('no marcó el regreso');
});

it('el volcado guarda la jornada, crea la fila de quien no vino y deja fuera a los agentes', function (): void {
    $this->travelTo(wdAt('2026-10-15 17:30'));
    $store = wdStore();

    UserActivityTracker::recordPing(601, 'tabA', true, 0, 'heartbeat', [], wdAt('2026-10-15 09:00')->getTimestamp());
    UserActivityTracker::recordPing(603, 'tabB', true, 0, 'heartbeat', [], wdAt('2026-10-15 09:00')->getTimestamp());
    WorkBreaks::start(wdUser(601), null, wdAt('2026-10-15 12:00'));

    $result = UserActivityFlusher::flush(UserActivityClock::now(), $store);
    $again = UserActivityFlusher::flush(UserActivityClock::now(), $store);
    $rows = UserActivityDay::query()->whereDate('activity_date', '2026-10-15')->get()->keyBy('user_id');

    /** Hoy: las 4 personas. Ayer: las 3 con jornada que no se conectaron (ausentes todo el día). */
    expect($result['days'])->toBe(7)
        ->and($again['days'])->toBe(7)
        ->and(UserActivityDay::query()->count())->toBe(7)
        ->and(UserActivityDay::query()->whereDate('activity_date', '2026-10-14')->pluck('work_absent_minutes')->unique()->values()->all())->toBe([540])
        ->and($rows->keys()->sort()->values()->all())->toBe([601, 602, 603, 604])
        ->and($rows[601]->workday_kind)->toBe('laboral')
        ->and($rows[601]->lunch_minutes)->toBe(60)
        ->and($rows[601]->lunch_excess_minutes)->toBe(240)
        ->and($rows[601]->work_active_minutes)->toBe(1)
        ->and($rows[601]->work_base_minutes)->toBe(480)
        ->and($rows[602]->online_minutes)->toBe(0)
        ->and($rows[602]->work_absent_minutes)->toBe(540)
        ->and($rows[603]->workday_kind)->toBeNull()
        ->and(UserWorkBreak::query()->first()->end_reason)->toBe(UserWorkBreak::END_AUTO)
        ->and(array_map(fn (CarbonImmutable $day): string => $day->toDateString(), UserActivityFlusher::daysToFlush(UserActivityClock::now())))->toBe(['2026-10-15', '2026-10-14']);
});

it('los días anteriores a la activación conservan el cálculo de antes', function (): void {
    config(['live-presence.activity.workday.since' => '2026-10-16']);
    $store = wdStore();

    UserActivityTracker::recordPing(601, 'tabA', true, 0, 'heartbeat', [], UserActivityClock::now()->getTimestamp());
    UserActivityFlusher::flush(UserActivityClock::now(), $store);

    expect(UserActivityDay::query()->count())->toBe(1)
        ->and(UserActivityDay::query()->first()->workday_kind)->toBeNull()
        ->and(UserActivityFlusher::daysToFlush(wdAt('2026-10-15 10:00')))->toHaveCount(1)
        ->and(WorkBreaks::status(wdUser(601), wdAt('2026-10-15 12:00'))['state'])->toBe('hidden')
        ->and(UserActivityLiveBoard::build(['state' => 'all'], UserActivityClock::now()->getTimestamp(), $store)['workday'])->toBeFalse();
});

it('el tablero en vivo muestra quién está en almuerzo, quién se pasó y quién está ausente', function (): void {
    $this->travelTo(wdAt('2026-10-15 13:15'));
    $store = wdStore();
    $now = UserActivityClock::now()->getTimestamp();

    UserActivityTracker::recordPing(604, 'tabN', true, 0, 'heartbeat', [], $now);
    WorkBreaks::start(wdUser(601), null, wdAt('2026-10-15 12:00'));
    UserWorkBreak::query()->create(['user_id' => 604, 'work_date' => '2026-10-15', 'started_at' => '2026-10-15 12:50:00']);

    $board = UserActivityLiveBoard::build(['state' => 'all'], $now, $store);
    $rows = collect($board['rows'])->keyBy('user_id');

    expect($board['workday'])->toBeTrue()
        ->and($rows[601]['presence'])->toBe('lunch_over')
        ->and($rows[604]['presence'])->toBe('lunch')
        ->and($rows[602]['presence'])->toBe('absent')
        ->and($rows[602]['workday']['work_absent'])->toBe(315)
        ->and($board['kpis'])->toMatchArray(['lunch' => 2, 'lunch_over' => 1, 'absent' => 1, 'today' => 1])
        ->and(array_column(UserActivityLiveBoard::build(['state' => 'lunch'], $now, $store)['rows'], 'user_id'))->toEqualCanonicalizing([601, 604])
        ->and(array_column(UserActivityLiveBoard::build(['state' => 'absent'], $now, $store)['rows'], 'user_id'))->toBe([602])
        ->and($rows[601]['bands'][0]['type'])->toBe('work');
});

it('el reporte suma la jornada por persona y ordena por uso de jornada', function (): void {
    UserActivityDay::factory()->createMany([
        ['user_id' => 601, 'activity_date' => '2026-10-13', 'online_minutes' => 450, 'active_minutes' => 400, 'workday_kind' => 'laboral', 'work_base_minutes' => 480, 'work_active_minutes' => 400, 'work_absent_minutes' => 20, 'lunch_minutes' => 60, 'lunch_excess_minutes' => 5, 'overtime_minutes' => 30],
        ['user_id' => 601, 'activity_date' => '2026-10-14', 'online_minutes' => 450, 'active_minutes' => 440, 'workday_kind' => 'laboral', 'work_base_minutes' => 480, 'work_active_minutes' => 440, 'lunch_minutes' => 60],
        ['user_id' => 602, 'activity_date' => '2026-10-14', 'online_minutes' => 0, 'active_minutes' => 0, 'workday_kind' => 'laboral', 'work_base_minutes' => 540, 'work_absent_minutes' => 540, 'guard_minutes' => 100, 'guard_present_minutes' => 80],
        ['user_id' => 603, 'activity_date' => '2026-10-14', 'online_minutes' => 100, 'active_minutes' => 90],
    ]);

    [$from, $to] = UserActivityReport::range('2026-10-12', '2026-10-15');
    $report = UserActivityReport::summary($from, $to, ['sort' => 'work_usage']);
    $rows = collect($report['rows'])->keyBy('user_id');

    expect($rows[601])->toMatchArray(['workday_days' => 2, 'work_base' => 960, 'work_active' => 840, 'work_usage' => 88, 'lunch_excess' => 5, 'overtime' => 30])
        ->and($rows[602])->toMatchArray(['work_usage' => 0, 'work_absent' => 540, 'guard' => 100, 'guard_presence' => 80])
        ->and($rows[603]['work_usage'])->toBeNull()
        ->and(array_column($report['rows'], 'user_id'))->toBe([601, 602, 603])
        ->and($report['totals'])->toMatchArray(['work_base' => 1500, 'work_active' => 840, 'work_usage' => 56]);
});

it('el detalle de un día cerrado usa las cifras guardadas y pinta la barra de jornada', function (): void {
    UserActivityDay::factory()->create([
        'user_id' => 601, 'activity_date' => '2026-10-14', 'online_minutes' => 300, 'active_minutes' => 300,
        'minute_states' => str_repeat('.', 480).str_repeat('a', 300).str_repeat('.', 660),
        'workday_kind' => 'laboral', 'work_base_minutes' => 540, 'work_active_minutes' => 300, 'work_absent_minutes' => 240,
    ]);

    $day = UserActivityReport::day(601, wdAt('2026-10-14'));

    expect($day['workday'])->toMatchArray(['base' => 540, 'work_active' => 300, 'usage' => 56, 'kind' => WorkDayKind::Workday])
        ->and($day['segments'][0]['kind'])->toBe(WorkMinuteKind::WorkActive)
        ->and($day['bands'][0]['type'])->toBe('work')
        ->and(UserActivityReport::day(603, wdAt('2026-10-14'))['workday'])->toBeNull();
});

it('el botón de almuerzo: salir, volver y la pregunta al retomar el trabajo', function (): void {
    Filament::setCurrentPanel('business');
    $this->travelTo(wdAt('2026-10-15 12:00'));
    $this->actingAs(User::query()->findOrFail(601));

    expect(WorkBreakToggle::shouldRender())->toBeTrue();

    $component = Livewire::test(WorkBreakToggle::class)
        ->assertSet('status.state', 'ready')
        ->call('mountAction', 'startLunch')
        ->call('callMountedAction')
        ->assertNotified('¡Buen provecho!')
        ->assertSet('status.state', 'on')
        ->assertSee('En almuerzo')
        ->assertSee('Volver del almuerzo');

    $this->travelTo(wdAt('2026-10-15 13:05'));

    $component->call('refreshStatus')
        ->call('mountAction', 'returnPrompt')
        ->assertActionMounted('returnPrompt')
        ->assertMountedActionModalSee('¿Ya volviste del almuerzo?')
        ->call('callMountedAction')
        ->assertNotified('¡De vuelta al trabajo!')
        ->assertSet('status.state', 'done')
        ->assertSet('status.minutes', 65);

    expect(UserWorkBreak::query()->first()->end_reason)->toBe(UserWorkBreak::END_PROMPT);

    $this->actingAs(User::query()->findOrFail(603));
    expect(WorkBreakToggle::shouldRender())->toBeFalse();
});

it('«No, sigo en almuerzo» calla la pregunta unos minutos', function (): void {
    Filament::setCurrentPanel('business');
    $this->travelTo(wdAt('2026-10-15 12:30'));
    $this->actingAs(User::query()->findOrFail(601));
    WorkBreaks::start(wdUser(601), null, wdAt('2026-10-15 12:00'));

    Livewire::test(WorkBreakToggle::class)
        ->call('snoozePrompt')
        ->assertSet('snoozeUntil', wdAt('2026-10-15 12:40')->getTimestamp());

    expect(UserWorkBreak::query()->first()->ended_at)->toBeNull();
});

it('cambiar la jornada y registrar ausencias pide el permiso y queda auditado', function (): void {
    expect(BusinessFilamentActionPermissionRegistry::all())->toHaveKey(BusinessFilamentActionPermissionRegistry::MANAGE_WORKDAYS);

    Filament::setCurrentPanel('business');
    wdActingAs(['NEGOCIOS'], 910);
    expect(UserActivityMonitor::canManageWorkdays())->toBeFalse();

    wdActingAs(['SUPERADMIN'], 911);
    expect(UserActivityMonitor::canManageWorkdays())->toBeTrue();

    Livewire::test(UserActivityMonitor::class)
        ->call('selectUser', 601)
        ->assertSee('Jornada de esta persona')
        ->assertSee('Estándar')
        ->call('mountAction', 'editWorkSchedule', ['userId' => 601])
        ->set('mountedActions.0.data.work_schedule', 'guardias')
        ->call('callMountedAction')
        ->assertHasNoActionErrors()
        ->assertNotified('Jornada actualizada')
        ->call('mountAction', 'registerWorkAbsence', ['userId' => 601])
        ->set('mountedActions.0.data.type', 'vacaciones')
        ->set('mountedActions.0.data.starts_on', '2026-10-19')
        ->set('mountedActions.0.data.ends_on', '2026-10-23')
        ->call('callMountedAction')
        ->assertHasNoActionErrors()
        ->assertNotified('Ausencia registrada')
        ->call('mountAction', 'registerWorkAbsence', ['userId' => 601])
        ->set('mountedActions.0.data.type', 'permiso')
        ->set('mountedActions.0.data.starts_on', '2026-10-22')
        ->set('mountedActions.0.data.ends_on', '2026-10-22')
        ->call('callMountedAction')
        ->assertNotified('Ya hay una ausencia en esas fechas')
        ->call('mountAction', 'registerWorkAbsence', ['userId' => 601])
        ->set('mountedActions.0.data.type', 'permiso')
        ->set('mountedActions.0.data.starts_on', '2026-10-28')
        ->set('mountedActions.0.data.ends_on', '2026-10-27')
        ->call('callMountedAction')
        ->assertHasActionErrors(['ends_on']);

    $absence = UserWorkAbsence::query()->sole();

    expect(DB::table('users')->where('id', 601)->value('work_schedule'))->toBe('guardias')
        ->and($absence->type)->toBe(WorkAbsenceType::Vacation)
        ->and($absence->created_by)->toBe(911)
        ->and(WorkDayPlanner::forDay(wdUser(601), wdAt('2026-10-20'))->kind)->toBe(WorkDayKind::Absence);

    Livewire::test(UserActivityMonitor::class)
        ->call('mountAction', 'deleteWorkAbsence', ['absenceId' => $absence->id])
        ->call('callMountedAction')
        ->assertNotified('Ausencia eliminada');

    expect(UserWorkAbsence::query()->count())->toBe(0)
        ->and(DB::table('logs')->pluck('action')->all())->toBe(['AUDIT_WORK_SCHEDULE_CHANGED', 'AUDIT_WORK_ABSENCE_CREATED', 'AUDIT_WORK_ABSENCE_DELETED']);
});

it('la pantalla en vivo explica la jornada y marca almuerzos y ausencias', function (): void {
    Filament::setCurrentPanel('business');
    $this->travelTo(wdAt('2026-10-15 12:20'));
    wdStore();
    wdActingAs(['SUPERADMIN']);

    UserActivityTracker::recordPing(604, 'tabN', true, 0, 'heartbeat', [], UserActivityClock::now()->getTimestamp());
    WorkBreaks::start(wdUser(601), null, wdAt('2026-10-15 12:00'));

    Livewire::test(UserActivityMonitor::class)
        ->assertOk()
        ->assertSee('Ausentes en jornada')
        ->assertSee('Uso de jornada hoy')
        ->assertSee('Horas extra hoy')
        ->call('filterState', 'lunch')
        ->assertSee('Ana Interna')
        ->assertSee('En almuerzo · 0:20')
        ->assertDontSee('Nora Nocturna')
        ->call('filterState', 'absent')
        ->assertSee('Dra. Externa')
        ->assertSee('No se ha conectado hoy')
        ->call('setTab', 'report')
        ->assertSee('Uso de jornada')
        ->assertSee('Exceso de almuerzo');
});

it('la descarga a Excel trae las cifras de jornada', function (): void {
    UserActivityDay::factory()->create(['user_id' => 601, 'activity_date' => '2026-10-14', 'online_minutes' => 450, 'active_minutes' => 400, 'workday_kind' => 'laboral', 'work_base_minutes' => 480, 'work_active_minutes' => 400, 'lunch_minutes' => 60, 'overtime_minutes' => 15]);

    wdActingAs(['SUPERADMIN'], 920);
    $csv = $this->get(route('business.user-activity.export', ['desde' => '2026-10-12', 'hasta' => '2026-10-15']))->assertOk()->streamedContent();

    expect($csv)->toContain('Uso de jornada (%)')
        ->and($csv)->toContain('Horas extra (min)')
        ->and($csv)->toContain('En reuniones (min)')
        ->and($csv)->toContain(';1;480;400;83;0;0;0;60;0;15;0;');
});

it('una reunión exige motivo, no se duplica y puede haber varias en el día', function (): void {
    $ana = wdUser(601);

    expect(fn () => WorkMeetings::start($ana, '  ', 'business', wdAt('2026-10-15 10:00')))->toThrow(WorkBreakException::class, 'Indica el motivo de la reunión')
        ->and(fn () => WorkMeetings::start(wdUser(603), 'Comité', 'business', wdAt('2026-10-15 10:00')))->toThrow(WorkBreakException::class, 'no tiene jornada laboral');

    $first = WorkMeetings::start($ana, '  Revisión   de casos ', 'business', wdAt('2026-10-15 10:00'));
    $again = WorkMeetings::start($ana, 'Otra cosa', 'business', wdAt('2026-10-15 10:05'));

    expect($again->id)->toBe($first->id)
        ->and($first->reason)->toBe('Revisión de casos')
        ->and(WorkMeetings::status($ana, wdAt('2026-10-15 10:40')))->toMatchArray(['open' => true, 'minutes' => 40, 'reason' => 'Revisión de casos', 'long' => false]);

    WorkMeetings::end($ana, UserWorkMeeting::END_MANUAL, wdAt('2026-10-15 11:00'));
    WorkMeetings::start($ana, 'Comité comercial', null, wdAt('2026-10-15 15:00'));
    WorkMeetings::end($ana, UserWorkMeeting::END_PROMPT, wdAt('2026-10-15 16:30'));

    expect(WorkMeetings::end($ana, UserWorkMeeting::END_MANUAL, wdAt('2026-10-15 16:40')))->toBeNull()
        ->and(WorkMeetings::status($ana, wdAt('2026-10-15 16:45')))->toMatchArray(['open' => false, 'today_count' => 2, 'today_minutes' => 150])
        ->and(WorkMeetings::forUsers([601], wdAt('2026-10-15'), wdAt('2026-10-15'))[601]['2026-10-15'])->toBe([
            ['start' => 600, 'end' => 660, 'reason' => 'Revisión de casos', 'end_reason' => 'manual'],
            ['start' => 900, 'end' => 990, 'reason' => 'Comité comercial', 'end_reason' => 'prompt'],
        ])
        ->and(DB::table('logs')->pluck('action')->all())->toBe(['AUDIT_WORK_MEETING_STARTED', 'AUDIT_WORK_MEETING_ENDED', 'AUDIT_WORK_MEETING_STARTED', 'AUDIT_WORK_MEETING_ENDED']);
});

it('la reunión cuenta como trabajo en la jornada y como horas extra fuera de ella, aunque no esté conectado', function (): void {
    $plan = WorkDayPlanner::forDay(wdUser(601), wdAt('2026-10-15'));
    $meetings = [
        ['start' => 600, 'end' => 720, 'reason' => 'Comité', 'end_reason' => 'manual'],
        ['start' => 1050, 'end' => 1110, 'reason' => 'Cliente en la tarde', 'end_reason' => 'manual'],
    ];
    $result = WorkDayClassifier::classify($plan, wdMinutes([[480, 600, 'a'], [780, 1020, 'a']]), ['start' => 720, 'end' => 780], 1440, [], 1440, $meetings);

    expect($result['summary'])->toMatchArray([
        'base' => 480, 'work_active' => 360, 'work_meeting' => 120, 'meeting' => 180, 'overtime' => 60, 'work_absent' => 0, 'usage' => 100,
    ])
        ->and($result['codes'][650])->toBe(WorkMinuteKind::Meeting->value)
        ->and($result['codes'][1060])->toBe(WorkMinuteKind::Meeting->value)
        ->and(collect($result['segments'])->firstWhere('from', 600)['note'])->toBe('Reunión: Comité')
        ->and(collect($result['segments'])->firstWhere('from', 1050)['note'])->toBe('Reunión: Cliente en la tarde · cuenta como horas extra');
});

it('una reunión abierta que empezó en la jornada no suma horas extra mientras siga abierta', function (): void {
    $plan = WorkDayPlanner::forDay(wdUser(601), wdAt('2026-10-15'));
    $result = WorkDayClassifier::classify($plan, [], null, 1140, [], 1440, [['start' => 900, 'end' => null, 'reason' => 'Larga']]);

    expect($result['summary'])->toMatchArray(['work_meeting' => 120, 'meeting' => 120, 'overtime' => 0, 'meeting_open' => true]);
});

it('entrar en reunión cierra el almuerzo y salir a almorzar cierra la reunión', function (): void {
    $ana = wdUser(601);

    WorkBreaks::start($ana, null, wdAt('2026-10-15 12:00'));
    WorkMeetings::start($ana, 'Llamada urgente', null, wdAt('2026-10-15 12:20'));

    expect(UserWorkBreak::query()->sole())->ended_at->format('H:i')->toBe('12:20')
        ->and(UserWorkBreak::query()->sole()->end_reason)->toBe(UserWorkBreak::END_MEETING);

    DB::table('user_work_breaks')->delete();
    WorkBreaks::start($ana, null, wdAt('2026-10-15 13:00'));

    expect(UserWorkMeeting::query()->sole()->ended_at->format('H:i'))->toBe('13:00')
        ->and(UserWorkMeeting::query()->sole()->end_reason)->toBe(UserWorkMeeting::END_LUNCH);
});

it('una reunión sin fin marcado se cierra sola: a la salida en la jornada y con una hora fuera de ella', function (): void {
    WorkMeetings::start(wdUser(601), 'Comité', null, wdAt('2026-10-15 15:00'));
    WorkMeetings::start(wdUser(602), 'Guardia con paciente', null, wdAt('2026-10-17 20:00'));

    expect(WorkMeetings::closeExpired(wdAt('2026-10-15 17:30')))->toBe(0)
        ->and(WorkMeetings::closeExpired(wdAt('2026-10-15 18:00')))->toBe(1)
        ->and(WorkMeetings::closeExpired(wdAt('2026-10-17 23:59')))->toBe(0)
        ->and(WorkMeetings::closeExpired(wdAt('2026-10-18 00:01')))->toBe(1)
        ->and(WorkMeetings::closeExpired(wdAt('2026-10-18 00:02')))->toBe(0);

    $meetings = UserWorkMeeting::query()->orderBy('id')->get();

    expect($meetings[0]->ended_at->format('Y-m-d H:i'))->toBe('2026-10-15 17:00')
        ->and($meetings[0]->end_reason)->toBe(UserWorkMeeting::END_AUTO)
        ->and($meetings[1]->ended_at->format('Y-m-d H:i'))->toBe('2026-10-17 21:00');
});

it('el tablero muestra quién está en reunión y avisa la reunión larga', function (): void {
    $this->travelTo(wdAt('2026-10-15 14:00'));
    $store = wdStore();
    $now = UserActivityClock::now()->getTimestamp();

    WorkMeetings::start(wdUser(601), 'Comité de riesgos', null, wdAt('2026-10-15 13:30'));
    WorkMeetings::start(wdUser(602), 'Junta médica', null, wdAt('2026-10-15 10:30'));

    $board = UserActivityLiveBoard::build(['state' => 'all'], $now, $store);
    $rows = collect($board['rows'])->keyBy('user_id');

    expect($rows[601]['presence'])->toBe('meeting')
        ->and($rows[602]['presence'])->toBe('meeting_long')
        ->and($rows[601]['meeting']['reason'])->toBe('Comité de riesgos')
        ->and($rows[602]['workday']['work_meeting'])->toBe(210)
        ->and($board['kpis'])->toMatchArray(['meeting' => 2, 'meeting_long' => 1])
        ->and(array_column(UserActivityLiveBoard::build(['state' => 'meeting'], $now, $store)['rows'], 'user_id'))->toEqualCanonicalizing([601, 602]);
});

it('el botón de reunión pide el motivo y se termina con un clic o al responder la pregunta', function (): void {
    Filament::setCurrentPanel('business');
    $this->travelTo(wdAt('2026-10-17 10:00'));
    $this->actingAs(User::query()->findOrFail(601));

    $component = Livewire::test(WorkBreakToggle::class)
        ->assertSet('meeting.available', true)
        ->assertSet('status.state', 'hidden')
        ->call('mountAction', 'startMeeting')
        ->call('callMountedAction')
        ->assertHasActionErrors(['reason' => 'required'])
        ->set('mountedActions.0.data.reason', 'Revisión de cierre de mes')
        ->call('callMountedAction')
        ->assertHasNoActionErrors()
        ->assertNotified('Quedaste en reunión')
        ->assertSet('meeting.open', true)
        ->assertSee('Revisión de cierre de mes');

    $this->travelTo(wdAt('2026-10-17 11:00'));

    $component->call('refreshStatus')
        ->call('mountAction', 'meetingPrompt')
        ->assertMountedActionModalSee('¿Ya terminó tu reunión?')
        ->call('callMountedAction')
        ->assertNotified('Reunión terminada')
        ->assertSet('meeting.open', false);

    expect(UserWorkMeeting::query()->sole()->end_reason)->toBe(UserWorkMeeting::END_PROMPT);
});

it('en un día laboral el almuerzo siempre está en el menú, también si no se declaró', function (): void {
    Filament::setCurrentPanel('business');
    $this->actingAs(User::query()->findOrFail(601));

    $this->travelTo(wdAt('2026-10-15 07:30'));
    Livewire::test(WorkBreakToggle::class)->assertSet('status.state', 'early')->assertSet('status.opens_at', '8:00 a. m.');

    $this->travelTo(wdAt('2026-10-15 16:30'));
    Livewire::test(WorkBreakToggle::class)->assertSet('status.state', 'ready');

    $this->travelTo(wdAt('2026-10-15 19:00'));
    Livewire::test(WorkBreakToggle::class)->assertSet('status.state', 'missed');
});

it('las acciones van en el menú de usuario y la barra solo muestra el indicador mientras corren', function (): void {
    Filament::setCurrentPanel('business');
    $this->travelTo(wdAt('2026-10-15 12:00'));
    $this->actingAs(User::query()->findOrFail(601));

    $menu = view('filament.hooks.work-break-menu-items')->render();

    expect($menu)->toContain('Entrar en reunión')
        ->and($menu)->toContain('Terminé la reunión')
        ->and($menu)->toContain('Salir a almorzar')
        ->and($menu)->toContain('Volver del almuerzo')
        ->and($menu)->toContain("\$dispatch('wbt-action', 'startMeeting')");

    Livewire::test(WorkBreakToggle::class)
        ->assertDontSee('wbt-pill wbt-on')
        ->assertSeeHtml('x-on:wbt-action.window');

    WorkBreaks::start(wdUser(601), null, wdAt('2026-10-15 12:00'));
    Livewire::test(WorkBreakToggle::class)->assertSeeHtml('wbt-pill wbt-on');

    $this->actingAs(User::query()->findOrFail(603));
    expect(trim(view('filament.hooks.work-break-menu-items')->render()))->toBe('');
});

it('el volcado y el reporte guardan y suman el tiempo en reunión', function (): void {
    $this->travelTo(wdAt('2026-10-15 17:30'));
    $store = wdStore();

    WorkMeetings::start(wdUser(601), 'Comité', null, wdAt('2026-10-15 09:00'));
    WorkMeetings::end(wdUser(601), UserWorkMeeting::END_MANUAL, wdAt('2026-10-15 11:00'));
    UserActivityFlusher::flush(UserActivityClock::now(), $store);

    $row = UserActivityDay::query()->where('user_id', 601)->whereDate('activity_date', '2026-10-15')->sole();
    [$from, $to] = UserActivityReport::range('2026-10-15', '2026-10-15');
    $report = collect(UserActivityReport::summary($from, $to)['rows'])->keyBy('user_id');

    expect($row->meeting_minutes)->toBe(120)
        ->and($row->work_meeting_minutes)->toBe(120)
        ->and($row->work_absent_minutes)->toBe(420)
        ->and($report[601])->toMatchArray(['meeting' => 120, 'work_meeting' => 120, 'work_usage' => 22]);
});
