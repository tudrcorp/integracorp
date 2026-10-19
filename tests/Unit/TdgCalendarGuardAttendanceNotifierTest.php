<?php

declare(strict_types=1);

use App\Enums\TdgCalendarGuardShift;
use App\Mail\TdgCalendarGuardAttendanceMail;
use App\Models\RrhhColaborador;
use App\Models\TdgCalendarDay;
use App\Models\TdgCalendarGuardAssignment;
use App\Support\TdgCalendar\TdgCalendarGuardAttendanceNotifier;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

function createTdgCalendarGuardAttendanceTables(): void
{
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.driver' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'database.connections.sqlite.prefix' => '',
        'database.connections.sqlite.foreign_key_constraints' => false,
    ]);

    DB::purge();
    DB::reconnect();

    $connection = DB::connection();

    if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
        test()->markTestSkipped('Este test solo puede ejecutarse con sqlite en memoria para no alterar la base de datos real.');
    }

    Schema::dropIfExists('tdg_calendar_guard_assignments');
    Schema::dropIfExists('tdg_calendar_days');
    Schema::dropIfExists('rrhh_colaboradors');

    Schema::create('rrhh_colaboradors', function (Blueprint $table): void {
        $table->id();
        $table->string('fullName')->nullable();
        $table->string('emailCorporativo')->nullable();
        $table->string('emailAlternativo')->nullable();
        $table->string('emailPersonal')->nullable();
        $table->string('status')->default('activo');
        $table->timestamps();
    });

    Schema::create('tdg_calendar_days', function (Blueprint $table): void {
        $table->id();
        $table->date('calendar_date')->unique();
        $table->unsignedBigInteger('updated_by_user_id')->nullable();
        $table->timestamps();
    });

    Schema::create('tdg_calendar_guard_assignments', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('tdg_calendar_day_id');
        $table->string('guard_shift');
        $table->unsignedBigInteger('rrhh_colaborador_id');
        $table->timestamps();
    });
}

it('describe el resultado del envío de guardias de operaciones', function (): void {
    $notifier = new TdgCalendarGuardAttendanceNotifier;

    config()->set('mail.default', 'smtp');

    expect($notifier->formatNotificationBody(['sent' => 0, 'skipped' => 0]))->toBe('')
        ->and($notifier->formatNotificationBody(['sent' => 1, 'skipped' => 0]))
        ->toBe('Se envió 1 correo de guardia de operaciones.')
        ->and($notifier->formatNotificationBody(['sent' => 3, 'skipped' => 2]))
        ->toBe('Se enviaron 3 correos de guardia de operaciones. 2 colaboradores no tienen correo registrado.');
});

it('identifica colaboradores involucrados en una modificación de guardia', function (): void {
    $previous = [
        10 => 'proveedores',
        20 => 'nocturna',
    ];
    $next = [
        10 => 'ils_capitado,proveedores',
        30 => 'nocturna',
    ];

    expect(TdgCalendarGuardAttendanceNotifier::colaboradorIdsModified($previous, $next))->toBe([10, 20])
        ->and(TdgCalendarGuardAttendanceNotifier::colaboradorIdsAdded($previous, $next))->toBe([30]);
});

it('envía un correo por colaborador con cada guardia asignada en el mes', function (): void {
    createTdgCalendarGuardAttendanceTables();
    Mail::fake();

    $becky = RrhhColaborador::query()->create([
        'fullName' => 'Becky Acosta',
        'emailCorporativo' => 'bacosta@tudrencasa.com',
    ]);
    $sinCorreo = RrhhColaborador::query()->create([
        'fullName' => 'Sin Correo',
        'emailCorporativo' => null,
    ]);

    $day = TdgCalendarDay::query()->create(['calendar_date' => '2026-10-16']);
    TdgCalendarGuardAssignment::query()->create([
        'tdg_calendar_day_id' => $day->id,
        'guard_shift' => TdgCalendarGuardShift::Proveedores->value,
        'rrhh_colaborador_id' => $becky->id,
    ]);
    TdgCalendarGuardAssignment::query()->create([
        'tdg_calendar_day_id' => $day->id,
        'guard_shift' => TdgCalendarGuardShift::Nocturna->value,
        'rrhh_colaborador_id' => $becky->id,
    ]);
    TdgCalendarGuardAssignment::query()->create([
        'tdg_calendar_day_id' => $day->id,
        'guard_shift' => TdgCalendarGuardShift::IlsCapitado->value,
        'rrhh_colaborador_id' => $sinCorreo->id,
    ]);

    $report = (new TdgCalendarGuardAttendanceNotifier)->notifyForMonth(Carbon::parse('2026-10-01'));

    expect($report['sent'])->toBe(1)
        ->and($report['skipped'])->toBe(1);

    Mail::assertSent(TdgCalendarGuardAttendanceMail::class, function (TdgCalendarGuardAttendanceMail $mail): bool {
        return $mail->hasTo('bacosta@tudrencasa.com')
            && $mail->payload['colaborador_name'] === 'Becky Acosta'
            && $mail->payload['is_update'] === false
            && $mail->payload['month_label'] === 'octubre 2026'
            && $mail->envelope()->subject === 'Tu guardia de operaciones · octubre 2026'
            && collect($mail->payload['assignments'])->pluck('shift_label')->all() === [
                'Guardia 2.1 Proveedores',
                'Guardia 3.0 Nocturna',
            ];
    });
});

it('marca el correo como actualización cuando la guardia de operaciones cambia', function (): void {
    createTdgCalendarGuardAttendanceTables();
    Mail::fake();

    $becky = RrhhColaborador::query()->create([
        'fullName' => 'Becky Acosta',
        'emailCorporativo' => 'bacosta@tudrencasa.com',
    ]);
    $day = TdgCalendarDay::query()->create(['calendar_date' => '2026-10-17']);
    TdgCalendarGuardAssignment::query()->create([
        'tdg_calendar_day_id' => $day->id,
        'guard_shift' => TdgCalendarGuardShift::Nocturna->value,
        'rrhh_colaborador_id' => $becky->id,
    ]);

    (new TdgCalendarGuardAttendanceNotifier)->notifyForMonth(Carbon::parse('2026-10-01'), true);

    Mail::assertSent(TdgCalendarGuardAttendanceMail::class, function (TdgCalendarGuardAttendanceMail $mail): bool {
        return $mail->payload['is_update'] === true
            && $mail->envelope()->subject === 'Actualización de tu guardia de operaciones · octubre 2026';
    });
});

it('conecta guardias operaciones con el envío por correo', function (): void {
    $trait = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Pages/Concerns/InteractsWithTdgHybridCalendar.php');
    $shell = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/pages/partials/corporate-calendar-shell.blade.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/mails/tdg-calendar-guard-attendance.blade.php');

    expect($trait)
        ->toContain('TdgCalendarGuardAttendanceNotifier')
        ->toContain('notifyMonthGuardAttendance')
        ->toContain('shouldShowTdgGuardAttendanceNotifyAction')
        ->toContain('canSendGuardAttendanceMail')
        ->toContain('UserNavigationAccess::isSuperAdmin')
        ->toContain('Enviar guardias por correo (modificaciones)')
        ->toContain('recordGuardAttendanceChanges');

    expect($shell)
        ->toContain('notifyMonthGuardAttendance')
        ->toContain('tdgGuardAttendanceNotifyButtonLabel')
        ->toContain('shouldShowTdgGuardAttendanceNotifyAction');

    expect($view)
        ->toContain('Tu guardia de operaciones')
        ->toContain('shift_label')
        ->toContain('Guardias Operaciones');
});
