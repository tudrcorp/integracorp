<?php

declare(strict_types=1);

use App\Filament\Operations\Pages\GuardiasMedicas;
use App\Models\MedicalGuard;
use App\Models\Permission;
use App\Models\User;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Operations\MedicalGuards\MedicalGuardAccess;
use App\Support\Operations\MedicalGuards\MedicalGuardCompliance;
use App\Support\Operations\MedicalGuards\MedicalGuardException;
use App\Support\Operations\MedicalGuards\MedicalGuardScheduler;
use App\Support\UserActivity\Workday\WorkDayClassifier;
use App\Support\UserActivity\Workday\WorkDayKind;
use App\Support\UserActivity\Workday\WorkDayPlanner;
use App\Support\UserActivity\Workday\WorkGuardSource;
use App\Support\UserActivity\Workday\WorkMinuteKind;
use App\Support\UserActivity\Workday\WorkScheduleType;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Guardias médicas. Todo va a una conexión sqlite `:memory:` propia, igual que
 * UserWorkdayTest: este test nunca toca la base real.
 */
beforeEach(function (): void {
    config([
        'cache.default' => 'array',
        'session.driver' => 'array',
        'live-presence.activity.workday.enabled' => true,
        'live-presence.activity.workday.since' => '2026-10-01',
        'live-presence.activity.workday.extra_holidays' => [],
        'live-presence.activity.workday.medical_guards_since' => '2026-10-01',
        'database.connections.medical_guards_testing' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
    ]);

    $this->previousConnection = config('database.default');
    config()->set('database.default', 'medical_guards_testing');
    DB::purge('medical_guards_testing');
    DB::setDefaultConnection('medical_guards_testing');

    expect(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(DB::connection()->getDatabaseName())->toBe(':memory:');

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('status')->nullable();
        $table->unsignedBigInteger('doctor_id')->nullable();
        $table->unsignedBigInteger('supplier_id')->nullable();
        $table->string('work_schedule')->nullable();
        $table->text('departament')->nullable();
        $table->boolean('is_proveedor_amd')->default(false);
        $table->timestamps();
    });

    Schema::create('telemedicine_doctors', function (Blueprint $table): void {
        $table->id();
        $table->string('full_name')->nullable();
        $table->string('status')->nullable();
        $table->string('managed_by')->nullable();
        $table->timestamps();
    });

    foreach ([
        'tdg_calendar_days' => static function (Blueprint $table): void {
            $table->id();
            $table->date('calendar_date');
            $table->timestamps();
        },
        'tdg_calendar_guard_assignments' => static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tdg_calendar_day_id');
            $table->string('guard_shift');
            $table->unsignedBigInteger('rrhh_colaborador_id');
            $table->timestamps();
        },
        'operation_on_call_users' => static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('rrhh_colaborador_id')->nullable();
            $table->string('email')->nullable();
            $table->string('hrs_init')->nullable();
            $table->string('hrs_end')->nullable();
            $table->string('date_OnCall')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        },
        'user_work_absences' => static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type')->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('reason')->nullable();
            $table->timestamps();
        },
        'user_activity_days' => static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('activity_date');
            $table->text('minute_states')->nullable();
            $table->timestamps();
        },
    ] as $name => $definition) {
        Schema::create($name, $definition);
    }

    /** La migración real, dos veces: es idempotente. */
    $migration = require base_path('database/migrations/2026_10_30_120000_create_medical_guards_tables.php');
    $migration->up();
    $migration->up();

    DB::table('telemedicine_doctors')->insert([
        ['id' => 1, 'full_name' => 'Dra. Uno', 'status' => 'ACTIVO', 'managed_by' => 'TDG'],
        ['id' => 2, 'full_name' => 'Dr. Dos', 'status' => 'ACTIVO', 'managed_by' => 'TDG'],
        ['id' => 3, 'full_name' => 'Dr. Proveedor', 'status' => 'ACTIVO', 'managed_by' => 'CORPORACION VMC, C.A. (ATENMEDI)'],
        ['id' => 4, 'full_name' => 'Dra. Inactiva', 'status' => 'INACTIVO', 'managed_by' => 'TDG'],
    ]);

    DB::table('users')->insert([
        ['id' => 701, 'name' => 'Dra. Uno', 'email' => 'uno@gmail.com', 'status' => 'ACTIVO', 'doctor_id' => 1],
        ['id' => 703, 'name' => 'Dr. Proveedor', 'email' => 'prov@gmail.com', 'status' => 'ACTIVO', 'doctor_id' => 3],
    ]);

    Cache::store('array')->flush();

    /** Jueves 15 de octubre de 2026, 10:00 en Caracas. */
    $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00', 'America/Caracas'));
    $this->siteA = (int) DB::table('medical_guard_sites')->where('name', 'Diagno Center 3 de Febrero')->value('id');
    $this->siteB = (int) DB::table('medical_guard_sites')->where('name', 'Diagnomóvil Mene Grande')->value('id');
});

afterEach(function (): void {
    DB::purge('medical_guards_testing');
    config()->set('database.default', $this->previousConnection);
    DB::setDefaultConnection($this->previousConnection);
});

function mgUser(int $id = 701): User
{
    return User::query()->findOrFail($id);
}

function mgPlan(string $date, int $id = 701): App\Support\UserActivity\Workday\WorkDayPlan
{
    return WorkDayPlanner::forDay(mgUser($id), CarbonImmutable::parse($date, 'America/Caracas'));
}

function mgStates(array $ranges): string
{
    $states = str_repeat('.', 1440);

    foreach ($ranges as [$from, $to, $code]) {
        for ($minute = $from; $minute < $to; $minute++) {
            $states[$minute] = $code;
        }
    }

    return $states;
}

it('siembra las dos sedes una sola vez aunque la migración corra dos veces', function (): void {
    expect(DB::table('medical_guard_sites')->pluck('name')->all())
        ->toBe(['Diagno Center 3 de Febrero', 'Diagnomóvil Mene Grande']);
});

it('ofrece solo médicos de TDG activos', function (): void {
    expect(MedicalGuardScheduler::doctorOptions())->toBe([2 => 'Dr. Dos', 1 => 'Dra. Uno']);
});

it('carga varios días y permite varios médicos en la misma sede y hora', function (): void {
    $created = MedicalGuardScheduler::create($this->siteA, 1, ['2026-10-19', '2026-10-20'], '07:00', '19:00', '  Refuerzo ', null);
    MedicalGuardScheduler::create($this->siteA, 2, ['2026-10-19'], '07:00', '19:00', null, null);

    expect($created)->toHaveCount(2)
        ->and($created->first()->notes)->toBe('Refuerzo')
        ->and(MedicalGuard::query()->count())->toBe(3);
});

it('carga n médicos juntos en la misma sede, día y horario, de una sola vez', function (): void {
    DB::table('telemedicine_doctors')->insert(array_map(
        static fn (int $id): array => ['id' => $id, 'full_name' => 'Médico '.$id, 'status' => 'ACTIVO', 'managed_by' => 'TDG'],
        range(10, 19),
    ));

    $doctors = [1, 2, ...range(10, 19)];
    $created = MedicalGuardScheduler::createForDoctors($this->siteA, $doctors, ['2026-10-19', '2026-10-20'], '07:00', '19:00', null, null);

    expect($created)->toHaveCount(24)
        ->and(MedicalGuard::query()->whereDate('guard_date', '2026-10-19')->distinct()->count('telemedicine_doctor_id'))->toBe(12)
        ->and(MedicalGuardScheduler::previewForDoctors([1, 2], ['2026-10-26'], '07:00', '19:00'))->toMatchArray(['count' => 2, 'minutes' => 1440, 'conflicts' => []]);
});

it('si un médico del grupo choca no se guarda nada y dice quién fue', function (): void {
    MedicalGuardScheduler::create($this->siteB, 2, ['2026-10-19'], '08:00', '12:00', null, null);

    expect(fn () => MedicalGuardScheduler::createForDoctors($this->siteA, [1, 2], ['2026-10-19'], '07:00', '19:00', null, null))
        ->toThrow(MedicalGuardException::class, 'Dr. Dos: El 19/10 choca');

    expect(MedicalGuard::query()->count())->toBe(1)
        ->and(fn () => MedicalGuardScheduler::createForDoctors($this->siteA, [], ['2026-10-19'], '07:00', '19:00', null, null))->toThrow(MedicalGuardException::class, 'al menos un médico');
});

it('una hora de fin igual o anterior al inicio termina al día siguiente', function (): void {
    $night = MedicalGuardScheduler::create($this->siteA, 1, ['2026-10-19'], '19:00', '07:00', null, null)->first();
    $full = MedicalGuardScheduler::create($this->siteA, 2, ['2026-10-19'], '08:00', '08:00', null, null)->first();

    expect($night->ends_at->format('Y-m-d H:i'))->toBe('2026-10-20 07:00')
        ->and($night->scheduleLabel())->toBe('7:00 p. m. – 7:00 a. m. (+1 día)')
        ->and($full->durationMinutes())->toBe(1440);
});

it('bloquea que un médico esté en dos guardias a la vez, también en otra sede, y no guarda a medias', function (): void {
    MedicalGuardScheduler::create($this->siteA, 1, ['2026-10-20'], '07:00', '19:00', null, null);

    expect(fn () => MedicalGuardScheduler::create($this->siteB, 1, ['2026-10-19', '2026-10-20'], '12:00', '20:00', null, null))
        ->toThrow(MedicalGuardException::class, 'Diagno Center 3 de Febrero');

    expect(MedicalGuard::query()->count())->toBe(1);
});

it('guardias pegadas no chocan', function (): void {
    MedicalGuardScheduler::create($this->siteA, 1, ['2026-10-19', '2026-10-20'], '08:00', '08:00', null, null);

    expect(MedicalGuard::query()->count())->toBe(2);
});

it('la guardia nocturna de ayer también choca con la de hoy', function (): void {
    MedicalGuardScheduler::create($this->siteA, 1, ['2026-10-19'], '19:00', '07:00', null, null);

    expect(MedicalGuardScheduler::preview(1, ['2026-10-20'], '06:00', '10:00')['conflicts'])->toHaveCount(1);
});

it('rechaza médicos ajenos o inactivos, horas y fechas inválidas, y carga vacía', function (int $doctor, array $dates, string $start, string $end, string $message): void {
    expect(fn () => MedicalGuardScheduler::create($this->siteA, $doctor, $dates, $start, $end, null, null))
        ->toThrow(MedicalGuardException::class, $message);
})->with([
    'proveedor' => [3, ['2026-10-19'], '07:00', '19:00', 'médicos de TDG activos'],
    'inactiva' => [4, ['2026-10-19'], '07:00', '19:00', 'médicos de TDG activos'],
    'no existe' => [99, ['2026-10-19'], '07:00', '19:00', 'médicos de TDG activos'],
    'hora mala' => [1, ['2026-10-19'], '25:00', '19:00', 'hora de inicio'],
    'fecha mala' => [1, ['2026-02-31'], '07:00', '19:00', 'fechas'],
    'sin días' => [1, [], '07:00', '19:00', 'Selecciona'],
]);

it('editar no choca consigo misma y anular libera el horario', function (): void {
    $guard = MedicalGuardScheduler::create($this->siteA, 1, ['2026-10-19'], '07:00', '19:00', null, null)->first();

    MedicalGuardScheduler::update($guard, $this->siteB, 1, '2026-10-19', '08:00', '20:00', null, null);
    expect($guard->fresh()->medical_guard_site_id)->toBe($this->siteB);

    MedicalGuardScheduler::cancel($guard, mgUser());

    expect(MedicalGuard::query()->count())->toBe(0)
        ->and(MedicalGuard::withTrashed()->first()->deleted_by_user_id)->toBe(701)
        ->and(MedicalGuardScheduler::preview(1, ['2026-10-19'], '07:00', '19:00')['conflicts'])->toBe([]);
});

it('copia una semana a las siguientes y salta lo que choca', function (): void {
    MedicalGuardScheduler::create($this->siteA, 1, ['2026-10-19', '2026-10-21'], '07:00', '19:00', null, null);
    MedicalGuardScheduler::create($this->siteB, 1, ['2026-10-26'], '08:00', '12:00', null, null);

    $result = MedicalGuardScheduler::copyWeek($this->siteA, '2026-10-20', 2, null);

    expect($result['created'])->toBe(3)
        ->and($result['skipped'])->toHaveCount(1)
        ->and($result['skipped'][0])->toContain('Dra. Uno el 26/10')
        ->and(fn () => MedicalGuardScheduler::copyWeek($this->siteA, '2026-12-01', 1, null))->toThrow(MedicalGuardException::class, 'no tiene guardias');
});

it('los botones rápidos salen de los horarios usados en esa sede', function (): void {
    MedicalGuardScheduler::create($this->siteA, 1, ['2026-10-19', '2026-10-20'], '07:00', '19:00', null, null);
    MedicalGuardScheduler::create($this->siteA, 2, ['2026-10-19'], '19:00', '07:00', null, null);

    expect(MedicalGuardScheduler::recentSchedules($this->siteA))->toBe([
        '07:00|19:00' => '7:00 a. m. – 7:00 p. m.',
        '19:00|07:00' => '7:00 p. m. – 7:00 a. m. (+1 día)',
    ])->and(MedicalGuardScheduler::recentSchedules($this->siteB))->toBe([]);
});

it('el médico de TDG va por guardias aunque su usuario siga en estándar', function (): void {
    expect(mgPlan('2026-10-19')->schedule)->toBe(WorkScheduleType::Guards)
        ->and(mgPlan('2026-10-19')->kind)->toBe(WorkDayKind::NoShift)
        ->and(mgPlan('2026-10-19', 703)->schedule)->toBe(WorkScheduleType::Standard);
});

it('la exenta se respeta en un médico de TDG', function (): void {
    DB::table('users')->where('id', 701)->update(['work_schedule' => WorkScheduleType::Exempt->value]);

    expect(mgPlan('2026-10-19')->kind)->toBe(WorkDayKind::Exempt);
});

it('la guardia médica es su jornada: sin horario de oficina, sin almuerzo y con la sede en la etiqueta', function (): void {
    MedicalGuardScheduler::create($this->siteA, 1, ['2026-10-19'], '07:00', '19:00', null, null);

    $plan = mgPlan('2026-10-19');

    expect($plan->kind)->toBe(WorkDayKind::MedicalGuard)
        ->and($plan->hasJornada())->toBeFalse()
        ->and($plan->lunchAllowed())->toBeFalse()
        ->and($plan->guards[0])->toMatchArray(['from' => 420, 'to' => 1140, 'overtime' => false, 'label' => 'Guardia médica · Diagno Center 3 de Febrero']);
});

it('fin de semana y feriado cuentan como guardia normal, no como horas extra', function (string $date): void {
    MedicalGuardScheduler::create($this->siteA, 1, [$date], '08:00', '20:00', null, null);

    $plan = mgPlan($date);
    $result = WorkDayClassifier::classify($plan, array_fill(600, 60, 'a'), null);

    expect($plan->kind)->toBe(WorkDayKind::MedicalGuard)
        ->and($plan->guards[0]['overtime'])->toBeFalse()
        ->and($result['summary'])->toMatchArray(['overtime' => 0, 'guard' => 720, 'base' => 0])
        ->and($result['codes'][600])->toBe(WorkMinuteKind::GuardActive->value);
})->with(['sábado' => '2026-10-24', 'domingo' => '2026-10-25', 'feriado de EE. UU.' => '2026-11-26']);

it('hay descanso tras la nocturna solo si no tiene otra guardia', function (): void {
    MedicalGuardScheduler::create($this->siteA, 1, ['2026-10-19', '2026-10-20'], '19:00', '07:00', null, null);

    expect(mgPlan('2026-10-20')->kind)->toBe(WorkDayKind::MedicalGuard)
        ->and(mgPlan('2026-10-21')->kind)->toBe(WorkDayKind::GuardRest);
});

it('rige solo desde su fecha de inicio: lo anterior conserva el cálculo de antes', function (): void {
    config(['live-presence.activity.workday.medical_guards_since' => '2026-10-20']);

    MedicalGuardScheduler::create($this->siteA, 1, ['2026-10-19', '2026-10-20'], '19:00', '07:00', null, null);

    $before = mgPlan('2026-10-19');

    expect($before->kind)->toBe(WorkDayKind::Workday)
        ->and($before->schedule)->toBe(WorkScheduleType::Standard)
        ->and($before->guards)->toBe([])
        ->and(mgPlan('2026-10-20')->kind)->toBe(WorkDayKind::MedicalGuard)
        ->and(mgPlan('2026-10-20')->schedule)->toBe(WorkScheduleType::Guards)
        ->and(mgPlan('2026-10-20')->guards[0]['from'])->toBe(1140);
});

it('sin configuración rige desde el 30 de octubre de 2026', function (): void {
    $workday = config('live-presence.activity.workday');
    unset($workday['medical_guards_since']);
    config(['live-presence.activity.workday' => $workday]);

    expect(App\Support\UserActivity\Workday\WorkdayConfig::medicalGuardsApplyOn('2026-10-29'))->toBeFalse()
        ->and(App\Support\UserActivity\Workday\WorkdayConfig::medicalGuardsApplyOn('2026-10-30'))->toBeTrue();
});

it('una guardia anulada desaparece de la jornada', function (): void {
    $guard = MedicalGuardScheduler::create($this->siteA, 1, ['2026-10-19'], '07:00', '19:00', null, null)->first();
    MedicalGuardScheduler::cancel($guard, null);

    expect(mgPlan('2026-10-19')->kind)->toBe(WorkDayKind::NoShift);
});

it('cargar una guardia invalida el plan de hoy guardado en caché', function (): void {
    expect(WorkDayPlanner::cachedToday(mgUser())->kind)->toBe(WorkDayKind::NoShift);

    MedicalGuardScheduler::create($this->siteA, 1, ['2026-10-15'], '07:00', '19:00', null, null);

    expect(WorkDayPlanner::cachedToday(mgUser())->kind)->toBe(WorkDayKind::MedicalGuard);
});

it('no mezcla las guardias de un usuario que no pertenece al grupo', function (): void {
    MedicalGuardScheduler::create($this->siteA, 1, ['2026-10-19'], '07:00', '19:00', null, null);

    $windows = WorkGuardSource::windows(new Collection([mgUser(703)]), CarbonImmutable::parse('2026-10-19'), CarbonImmutable::parse('2026-10-19'));

    expect($windows)->toBe([]);
});

it('cruza la guardia con la actividad: con actividad, parcial, sin actividad, en curso y programada', function (): void {
    $active = MedicalGuardScheduler::create($this->siteA, 1, ['2026-10-12'], '08:00', '12:00', null, null)->first();
    $partial = MedicalGuardScheduler::create($this->siteA, 1, ['2026-10-13'], '08:00', '12:00', null, null)->first();
    $none = MedicalGuardScheduler::create($this->siteA, 1, ['2026-10-14'], '08:00', '12:00', null, null)->first();
    $running = MedicalGuardScheduler::create($this->siteA, 1, ['2026-10-15'], '08:00', '12:00', null, null)->first();
    $future = MedicalGuardScheduler::create($this->siteA, 1, ['2026-10-16'], '08:00', '12:00', null, null)->first();

    DB::table('user_activity_days')->insert([
        ['user_id' => 701, 'activity_date' => '2026-10-12', 'minute_states' => mgStates([[480, 620, 'a'], [620, 700, 'i']])],
        ['user_id' => 701, 'activity_date' => '2026-10-13', 'minute_states' => mgStates([[480, 500, 'a']])],
        ['user_id' => 701, 'activity_date' => '2026-10-14', 'minute_states' => mgStates([[800, 900, 'a']])],
        ['user_id' => 701, 'activity_date' => '2026-10-15', 'minute_states' => mgStates([[480, 540, 'a']])],
    ]);

    $results = MedicalGuardCompliance::evaluate(MedicalGuard::query()->get());

    expect($results[$active->id])->toBe(['state' => MedicalGuardCompliance::ACTIVE, 'percent' => 92])
        ->and($results[$partial->id])->toBe(['state' => MedicalGuardCompliance::PARTIAL, 'percent' => 8])
        ->and($results[$none->id])->toBe(['state' => MedicalGuardCompliance::NONE, 'percent' => 0])
        ->and($results[$running->id])->toBe(['state' => MedicalGuardCompliance::RUNNING, 'percent' => 50])
        ->and($results[$future->id])->toBe(['state' => MedicalGuardCompliance::SCHEDULED, 'percent' => null]);
});

it('cuenta la cola de madrugada de una guardia y avisa si el médico no tiene usuario o ya no hay detalle', function (): void {
    $night = MedicalGuardScheduler::create($this->siteA, 1, ['2026-10-12'], '20:00', '04:00', null, null)->first();
    $noUser = MedicalGuardScheduler::create($this->siteA, 2, ['2026-10-12'], '08:00', '12:00', null, null)->first();
    $old = MedicalGuardScheduler::create($this->siteA, 1, ['2026-10-05'], '08:00', '12:00', null, null)->first();

    DB::table('user_activity_days')->insert([
        ['user_id' => 701, 'activity_date' => '2026-10-13', 'minute_states' => mgStates([[0, 240, 'a']])],
        ['user_id' => 701, 'activity_date' => '2026-10-05', 'minute_states' => null],
    ]);

    $results = MedicalGuardCompliance::evaluate(MedicalGuard::query()->get());

    expect($results[$night->id])->toBe(['state' => MedicalGuardCompliance::ACTIVE, 'percent' => 50])
        ->and($results[$noUser->id]['state'])->toBe(MedicalGuardCompliance::NO_USER)
        ->and($results[$old->id]['state'])->toBe(MedicalGuardCompliance::NO_DETAIL);
});

it('todo Operaciones las ve, pero cargarlas pide el permiso y los proveedores quedan fuera', function (): void {
    $analyst = new User(['departament' => ['OPERACIONES']]);
    $analyst->setRelation('permissions', new Collection);

    $loader = new User(['departament' => ['OPERACIONES']]);
    $loader->setRelation('permissions', new Collection([new Permission(['slug' => BusinessFilamentActionPermissionRegistry::MANAGE_MEDICAL_GUARDS, 'module' => 'OPERACIONES'])]));

    $supplier = new User(['departament' => ['OPERACIONES']]);
    $supplier->supplier_id = 15;
    $supplier->setRelation('permissions', new Collection);

    $other = new User(['departament' => ['NEGOCIOS']]);
    $other->setRelation('permissions', new Collection);

    expect(MedicalGuardAccess::canView($analyst))->toBeTrue()
        ->and(MedicalGuardAccess::canManage($analyst))->toBeFalse()
        ->and(MedicalGuardAccess::canManage($loader))->toBeTrue()
        ->and(MedicalGuardAccess::canView($supplier))->toBeFalse()
        ->and(MedicalGuardAccess::canView($other))->toBeFalse()
        ->and(MedicalGuardAccess::canView(null))->toBeFalse();
});

it('la página no crea permiso de menú y el permiso de acción existe en Operaciones', function (): void {
    expect(DepartmentNavigationPermissionRegistry::isManagedOutsideNavigationPermissions(GuardiasMedicas::class))->toBeTrue()
        ->and(BusinessFilamentActionPermissionRegistry::slugIsAvailableInModule('gestionar-guardias-medicas', 'OPERACIONES'))->toBeTrue();
});

it('renderiza el PDF del mes', function (): void {
    MedicalGuardScheduler::create($this->siteA, 1, ['2026-10-19'], '07:00', '19:00', null, null);

    $html = view('documents.medical-guards-schedule', [
        'monthLabel' => 'Octubre 2026',
        'sites' => MedicalGuardScheduler::between(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'))->groupBy(static fn (MedicalGuard $guard): string => (string) $guard->site?->name),
    ])->render();

    expect($html)->toContain('Diagno Center 3 de Febrero')->toContain('Dra. Uno')->toContain('7:00 a. m. – 7:00 p. m.');
});

function mgPage(array $departments = ['SUPERADMIN']): Testable
{
    Filament::setCurrentPanel(Filament::getPanel('operations'));

    $user = new User(['name' => 'Analista', 'email' => 'analista@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => $departments]);
    $user->id = 900;
    $user->setRelation('permissions', new Collection);
    Auth::setUser($user);

    return Livewire::test(GuardiasMedicas::class);
}

/**
 * Filament pinta las modales en un parcial de Livewire, aparte del HTML de la página.
 */
function mgModalHtml(Testable $page): string
{
    return html_entity_decode((string) json_encode($page->effects['partials'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

it('la pantalla carga las guardias de los días marcados y limpia la selección', function (): void {
    mgPage()
        ->assertSee('Diagno Center 3 de Febrero')
        ->assertSee('Octubre 2026')
        ->set('selectedDates', ['2026-10-19', '2026-10-20'])
        ->mountAction('loadGuards')
        ->set('mountedActions.0.data.doctor_ids', [1])
        ->set('mountedActions.0.data.start', '07:00')
        ->set('mountedActions.0.data.end', '19:00')
        ->tap(fn (Testable $page) => expect(mgModalHtml($page))->toContain('2 guardias · 24 h · 7:00 a. m. – 7:00 p. m.'))
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('2 guardias cargadas')
        ->assertSet('selectedDates', [])
        ->assertSee('Dra. Uno');

    expect(MedicalGuard::query()->count())->toBe(2)
        ->and(MedicalGuard::query()->first()->created_by_user_id)->toBe(900);
});

it('la pantalla carga varios médicos juntos y lo enseña en el resumen', function (): void {
    mgPage()
        ->set('selectedDates', ['2026-10-19'])
        ->mountAction('loadGuards')
        ->set('mountedActions.0.data.doctor_ids', [1, 2])
        ->set('mountedActions.0.data.start', '07:00')
        ->set('mountedActions.0.data.end', '19:00')
        ->tap(fn (Testable $page) => expect(mgModalHtml($page))->toContain('2 médicos · 2 guardias · 24 h'))
        ->callMountedAction()
        ->assertNotified('2 guardias cargadas')
        ->assertSee('Dra. Uno')
        ->assertSee('Dr. Dos')
        ->assertSee('2 guardias');

    expect(MedicalGuard::query()->count())->toBe(2);
});

it('la pantalla enseña el choque antes de guardar, no guarda y conserva la selección', function (): void {
    MedicalGuardScheduler::create($this->siteB, 1, ['2026-10-19'], '07:00', '19:00', null, null);

    mgPage()
        ->set('selectedDates', ['2026-10-19'])
        ->mountAction('loadGuards')
        ->set('mountedActions.0.data.doctor_ids', [1])
        ->set('mountedActions.0.data.start', '08:00')
        ->set('mountedActions.0.data.end', '12:00')
        ->tap(fn (Testable $page) => expect(mgModalHtml($page))->toContain('choca con su guardia en Diagnomóvil Mene Grande'))
        ->callMountedAction()
        ->assertNotified('No se pudo guardar')
        ->assertSet('selectedDates', ['2026-10-19']);

    expect(MedicalGuard::query()->count())->toBe(1);
});

it('la pantalla edita y anula una guardia', function (): void {
    $guard = MedicalGuardScheduler::create($this->siteA, 1, ['2026-10-19'], '07:00', '19:00', null, null)->first();

    mgPage()
        ->mountAction('editGuard', ['guard' => $guard->id])
        ->set('mountedActions.0.data.end', '20:00')
        ->callMountedAction()
        ->assertNotified('Guardia actualizada');

    expect($guard->fresh()->ends_at->format('H:i'))->toBe('20:00');

    mgPage()
        ->mountAction('cancelGuard', ['guard' => $guard->id])
        ->callMountedAction()
        ->assertNotified('Guardia anulada');

    expect(MedicalGuard::query()->count())->toBe(0);
});

it('quien solo consulta ve el calendario sin ninguna acción de carga', function (): void {
    MedicalGuardScheduler::create($this->siteA, 1, ['2026-10-19'], '07:00', '19:00', null, null);

    mgPage(['OPERACIONES'])
        ->assertSee('Dra. Uno')
        ->assertSee('Estás consultando las guardias')
        ->assertActionHidden('loadGuards')
        ->assertActionHidden('cancelGuard')
        ->assertActionHidden('copyWeek');
});

it('cambia de mes y de sede sin arrastrar la selección a otra sede', function (): void {
    mgPage()
        ->set('selectedDates', ['2026-10-19'])
        ->call('changeMonth', 3)
        ->assertSet('month', '2027-01')
        ->assertSee('Enero 2027')
        ->call('goToCurrentMonth')
        ->assertSet('month', '2026-10')
        ->call('selectSite', $this->siteB)
        ->assertSet('siteId', $this->siteB)
        ->assertSet('selectedDates', [])
        ->call('selectSite', 9999)
        ->assertSet('siteId', $this->siteB);
});
