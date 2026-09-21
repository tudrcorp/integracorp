<?php

declare(strict_types=1);

use App\Enums\TdgCalendarOffice;
use App\Mail\TdgCalendarOfficeAttendanceMail;
use App\Models\RrhhColaborador;
use App\Models\TdgCalendarDay;
use App\Models\TdgCalendarOfficeAssignment;
use App\Support\TdgCalendar\TdgCalendarOfficeAttendanceNotifier;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

function createTdgCalendarOfficeAttendanceTables(): void
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

    Schema::dropIfExists('tdg_calendar_office_assignments');
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

    Schema::create('tdg_calendar_office_assignments', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('tdg_calendar_day_id');
        $table->string('office');
        $table->unsignedBigInteger('rrhh_colaborador_id');
        $table->timestamps();
    });
}

it('prioriza el correo corporativo del colaborador para notificar asistencia', function (): void {
    $notifier = new TdgCalendarOfficeAttendanceNotifier;
    $colaborador = new RrhhColaborador([
        'emailCorporativo' => 'ana@tudrencasa.com',
        'emailAlternativo' => 'ana.alt@tudrencasa.com',
        'emailPersonal' => 'ana@gmail.com',
    ]);

    expect($notifier->resolveEmail($colaborador))->toBe('ana@tudrencasa.com');

    $withoutCorporate = new RrhhColaborador([
        'emailCorporativo' => null,
        'emailAlternativo' => 'ana.alt@tudrencasa.com',
        'emailPersonal' => 'ana@gmail.com',
    ]);

    expect($notifier->resolveEmail($withoutCorporate))->toBe('ana.alt@tudrencasa.com');

    $withoutEmail = new RrhhColaborador([
        'emailCorporativo' => '',
        'emailAlternativo' => 'no-es-correo',
        'emailPersonal' => null,
    ]);

    expect($notifier->resolveEmail($withoutEmail))->toBeNull();
});

it('describe el resultado del envío de asistencia a oficina', function (): void {
    $notifier = new TdgCalendarOfficeAttendanceNotifier;

    config()->set('mail.default', 'smtp');

    expect($notifier->formatNotificationBody(['sent' => 0, 'skipped' => 0]))->toBe('')
        ->and($notifier->formatNotificationBody(['sent' => 1, 'skipped' => 0]))
        ->toBe('Se envió 1 correo de asistencia a oficina.')
        ->and($notifier->formatNotificationBody(['sent' => 3, 'skipped' => 2]))
        ->toBe('Se enviaron 3 correos de asistencia a oficina. 2 colaboradores no tienen correo registrado.');
});

it('advierte cuando el mailer local solo escribe en el log', function (): void {
    config()->set('mail.default', 'log');

    expect((new TdgCalendarOfficeAttendanceNotifier)->formatNotificationBody(['sent' => 1, 'skipped' => 0]))
        ->toContain('guardó en el log')
        ->toContain('no llegó a bandejas reales');
});

it('identifica colaboradores involucrados en una modificación de oficina', function (): void {
    $previous = [
        10 => 'central_lido',
        20 => 'farmadoc_san_bernardino',
    ];
    $next = [
        10 => 'farmadoc_las_delicias',
        30 => 'farmadoc_san_bernardino',
    ];

    expect(TdgCalendarOfficeAttendanceNotifier::colaboradorIdsModified($previous, $next))
        ->toEqualCanonicalizing([10, 20])
        ->and(TdgCalendarOfficeAttendanceNotifier::colaboradorIdsAdded($previous, $next))
        ->toEqualCanonicalizing([30])
        ->and(TdgCalendarOfficeAttendanceNotifier::colaboradorIdsWithAssignmentChanges($previous, $next))
        ->toEqualCanonicalizing([10, 20, 30]);
});

it('envía un correo por colaborador con su oficina asignada en el mes', function (): void {
    createTdgCalendarOfficeAttendanceTables();
    Mail::fake();

    $ana = RrhhColaborador::query()->create([
        'fullName' => 'Ana Pérez',
        'emailCorporativo' => 'ana@tudrencasa.com',
    ]);
    $luis = RrhhColaborador::query()->create([
        'fullName' => 'Luis Mora',
        'emailCorporativo' => 'luis@tudrencasa.com',
    ]);
    $sinCorreo = RrhhColaborador::query()->create([
        'fullName' => 'Sin Correo',
        'emailCorporativo' => null,
        'emailPersonal' => null,
    ]);

    $monday = TdgCalendarDay::query()->create(['calendar_date' => '2026-09-21']);
    $wednesday = TdgCalendarDay::query()->create(['calendar_date' => '2026-09-23']);

    TdgCalendarOfficeAssignment::query()->create([
        'tdg_calendar_day_id' => $monday->id,
        'office' => TdgCalendarOffice::CentralLido->value,
        'rrhh_colaborador_id' => $ana->id,
    ]);
    TdgCalendarOfficeAssignment::query()->create([
        'tdg_calendar_day_id' => $wednesday->id,
        'office' => TdgCalendarOffice::FarmadocLasDelicias->value,
        'rrhh_colaborador_id' => $ana->id,
    ]);
    TdgCalendarOfficeAssignment::query()->create([
        'tdg_calendar_day_id' => $monday->id,
        'office' => TdgCalendarOffice::FarmadocSanBernardino->value,
        'rrhh_colaborador_id' => $luis->id,
    ]);
    TdgCalendarOfficeAssignment::query()->create([
        'tdg_calendar_day_id' => $monday->id,
        'office' => TdgCalendarOffice::FarmadocLasDelicias->value,
        'rrhh_colaborador_id' => $sinCorreo->id,
    ]);

    $report = (new TdgCalendarOfficeAttendanceNotifier)->notifyForDates(
        ['2026-09-21'],
        null,
        false,
    );

    expect($report['sent'])->toBe(2)
        ->and($report['skipped'])->toBe(1);

    Mail::assertSent(TdgCalendarOfficeAttendanceMail::class, 2);

    Mail::assertSent(TdgCalendarOfficeAttendanceMail::class, function (TdgCalendarOfficeAttendanceMail $mail): bool {
        return $mail->hasTo('ana@tudrencasa.com')
            && $mail->payload['colaborador_name'] === 'Ana Pérez'
            && $mail->payload['is_update'] === false
            && $mail->payload['month_label'] === 'septiembre 2026'
            && collect($mail->payload['assignments'])->pluck('office_label')->all() === [
                'Centro Lido',
                'Farmadoc (Las Delicias)',
            ];
    });

    Mail::assertSent(TdgCalendarOfficeAttendanceMail::class, function (TdgCalendarOfficeAttendanceMail $mail): bool {
        return $mail->hasTo('luis@tudrencasa.com')
            && $mail->envelope()->subject === 'Tu asistencia a oficina · septiembre 2026';
    });
});

it('marca el correo como actualización cuando el calendario de oficinas cambia', function (): void {
    createTdgCalendarOfficeAttendanceTables();
    Mail::fake();

    $ana = RrhhColaborador::query()->create([
        'fullName' => 'Ana Pérez',
        'emailCorporativo' => 'ana@tudrencasa.com',
    ]);

    $day = TdgCalendarDay::query()->create(['calendar_date' => '2026-10-05']);
    TdgCalendarOfficeAssignment::query()->create([
        'tdg_calendar_day_id' => $day->id,
        'office' => TdgCalendarOffice::CentralLido->value,
        'rrhh_colaborador_id' => $ana->id,
    ]);

    (new TdgCalendarOfficeAttendanceNotifier)->notifyForMonth(Carbon::parse('2026-10-01'), true);

    Mail::assertSent(TdgCalendarOfficeAttendanceMail::class, function (TdgCalendarOfficeAttendanceMail $mail): bool {
        return $mail->payload['is_update'] === true
            && $mail->envelope()->subject === 'Actualización de tu asistencia a oficina · octubre 2026';
    });
});

it('conecta el calendario tdg con el envío de asistencia por correo', function (): void {
    $traitPath = dirname(__DIR__, 2).'/app/Filament/Business/Pages/Concerns/InteractsWithTdgHybridCalendar.php';
    $shellPath = dirname(__DIR__, 2).'/resources/views/filament/business/pages/partials/corporate-calendar-shell.blade.php';
    $mailPath = dirname(__DIR__, 2).'/app/Mail/TdgCalendarOfficeAttendanceMail.php';
    $viewPath = dirname(__DIR__, 2).'/resources/views/mails/tdg-calendar-office-attendance.blade.php';

    expect(file_get_contents($traitPath))
        ->toContain('TdgCalendarOfficeAttendanceNotifier')
        ->toContain('notifyMonthOfficeAttendance')
        ->toContain('recordOfficeAttendanceModifications')
        ->toContain('recordOfficeAttendanceNewAssignments')
        ->toContain('hasPendingOfficeAttendanceModifications')
        ->toContain('hasPendingOfficeAttendanceNewAssignments')
        ->toContain('tdgOfficeAttendanceNotifyButtonLabel')
        ->toContain('Enviar asistencia por correo (modificaciones)')
        ->toContain('pendingOfficeAttendanceColaboradorIds')
        ->toContain('pendingOfficeAttendanceNewColaboradorIds')
        ->toContain('officeAssignmentMapForDate')
        ->toContain('shouldShowTdgOfficeAttendanceNotifyAction');

    expect(file_get_contents($shellPath))
        ->toContain('notifyMonthOfficeAttendance')
        ->toContain('tdgOfficeAttendanceNotifyButtonLabel')
        ->toContain('shouldShowTdgOfficeAttendanceNotifyAction');

    expect(file_get_contents($mailPath))
        ->toContain('mails.tdg-calendar-office-attendance')
        ->toContain('Actualización de tu asistencia a oficina')
        ->not->toContain('ShouldQueue');

    expect(file_get_contents($viewPath))
        ->toContain('Tu asistencia a oficina')
        ->toContain('office_label')
        ->toContain('jornada que debes efectuar');
});
