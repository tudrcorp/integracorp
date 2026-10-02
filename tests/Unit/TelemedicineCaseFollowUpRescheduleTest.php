<?php

declare(strict_types=1);

use App\Models\TelemedicineCase;
use App\Models\TelemedicineCaseFollowUpReschedule;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicineOperationsLog;
use App\Models\User;
use App\Support\Operations\TelemedicineCaseBitacora;
use App\Support\Telemedicine\TelemedicineCaseFollowUpRescheduler;
use App\Support\Telemedicine\TelemedicineCaseFollowUpSchedule;
use App\Support\Telemedicine\TelemedicineConsultationSigningDoctor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    TelemedicineConsultationSigningDoctor::flush();
});
afterEach(fn () => DB::rollBack());

/**
 * Caso TDG real EN SEGUIMIENTO y un usuario médico TDG para atenderlo.
 *
 * @return array{case: TelemedicineCase, user: User}
 */
function followUpFixture(): array
{
    $case = TelemedicineCase::query()
        ->where('status', 'EN SEGUIMIENTO')
        ->whereHas('telemedicineDoctor', fn ($doctor) => $doctor->where('managed_by', 'TDG'))
        ->whereHas('consultations')
        ->orderByDesc('id')
        ->first();

    $user = User::query()
        ->whereIn('doctor_id', TelemedicineDoctor::query()->where('managed_by', 'TDG')->select('id'))
        ->first();

    if ($case === null || $user === null) {
        test()->markTestSkipped('La base no tiene un caso TDG EN SEGUIMIENTO con consultas o un usuario médico TDG.');
    }

    return ['case' => $case, 'user' => $user];
}

function nextFollowUpOf(TelemedicineCase $case): ?string
{
    $value = TelemedicineCaseFollowUpSchedule::withFollowUpColumns(TelemedicineCase::query()->whereKey($case->id))->value('next_follow_up_at');

    return $value === null ? null : Carbon::parse((string) $value)->format('Y-m-d H:i:s');
}

function latestConsultationAt(TelemedicineCase $case): Carbon
{
    return Carbon::parse((string) TelemedicineConsultationPatient::query()->where('telemedicine_case_id', $case->id)->orderByDesc('id')->value('created_at'));
}

it('reasigna el seguimiento contando desde el momento de la reasignación', function (int $interval, int $minutes): void {
    ['case' => $case, 'user' => $user] = followUpFixture();
    $now = now()->startOfSecond();

    $reschedule = TelemedicineCaseFollowUpRescheduler::reschedule($case, $interval, '  Paciente pide   llamada más tarde ', $user, $now);

    expect($reschedule->exists)->toBeTrue()
        ->and($reschedule->priority_monitoring)->toBe($interval)
        ->and($reschedule->observation)->toBe('Paciente pide llamada más tarde')
        ->and($reschedule->user_id)->toBe($user->id)
        ->and($reschedule->telemedicine_doctor_id)->toBe((int) $user->doctor_id)
        ->and(nextFollowUpOf($case))->toBe($now->copy()->addMinutes($minutes)->format('Y-m-d H:i:s'));
})->with([
    '60 minutos' => [60, 60],
    '180 minutos' => [180, 180],
    '24 horas' => [24, 1440],
    '72 horas' => [72, 4320],
]);

it('registra la reasignación en la bitácora operativa del caso con la observación', function (): void {
    ['case' => $case, 'user' => $user] = followUpFixture();
    $before = TelemedicineOperationsLog::query()->where('telemedicine_case_id', $case->id)->count();

    TelemedicineCaseFollowUpRescheduler::reschedule($case, 90, 'Cambio de guardia, retomar en la tarde', $user, now()->startOfSecond());

    $log = TelemedicineOperationsLog::query()->where('telemedicine_case_id', $case->id)->latest('id')->firstOrFail();

    expect(TelemedicineOperationsLog::query()->where('telemedicine_case_id', $case->id)->count())->toBe($before + 1)
        ->and($log->operation)->toBe(TelemedicineCaseFollowUpRescheduler::OPERATION)
        ->and($log->observations)->toBe('Cambio de guardia, retomar en la tarde')
        ->and($log->status)->toBe('EN SEGUIMIENTO')
        ->and($log->description)->toContain('90 minutos')->toContain('antes:');

    $entries = collect(TelemedicineCaseBitacora::dossier($case->fresh())['operation_logs']);

    expect($entries->pluck('Operación')->all())->toContain(TelemedicineCaseFollowUpRescheduler::OPERATION)
        ->and($entries->pluck('Observaciones')->all())->toContain('Cambio de guardia, retomar en la tarde');
});

it('guarda el próximo seguimiento que había antes de reasignar', function (): void {
    ['case' => $case, 'user' => $user] = followUpFixture();
    $previous = nextFollowUpOf($case);

    $reschedule = TelemedicineCaseFollowUpRescheduler::reschedule($case, 30, 'Se adelanta el control', $user, now()->startOfSecond());

    expect($reschedule->previous_next_follow_up_at?->format('Y-m-d H:i:s'))->toBe($previous);
});

it('una consulta posterior vuelve a mandar sobre la reasignación', function (): void {
    ['case' => $case, 'user' => $user] = followUpFixture();
    $fromConsultation = nextFollowUpOf($case);

    TelemedicineCaseFollowUpRescheduler::reschedule($case, 72, 'Reasignación anterior a la última consulta', $user, latestConsultationAt($case)->subMinute());

    expect(nextFollowUpOf($case))->toBe($fromConsultation);
});

it('rechaza un intervalo que no está en la lista sin escribir nada', function (mixed $interval): void {
    ['case' => $case, 'user' => $user] = followUpFixture();
    $before = TelemedicineCaseFollowUpReschedule::query()->count();

    expect(fn () => TelemedicineCaseFollowUpRescheduler::reschedule($case, $interval, 'Observación válida', $user))
        ->toThrow(DomainException::class, 'Seleccione un «Próximo seguimiento» de la lista.');

    expect(TelemedicineCaseFollowUpReschedule::query()->count())->toBe($before);
})->with([
    'fuera de la lista' => [45],
    'cero' => [0],
    'negativo' => [-60],
    'texto' => ['abc'],
    'nulo' => [null],
]);

it('exige una observación de entre 5 y 255 caracteres', function (mixed $observation): void {
    ['case' => $case, 'user' => $user] = followUpFixture();

    expect(fn () => TelemedicineCaseFollowUpRescheduler::reschedule($case, 60, $observation, $user))
        ->toThrow(DomainException::class);
})->with([
    'vacía' => [''],
    'solo espacios' => ['      '],
    'corta' => ['ok'],
    'nula' => [null],
    'larga' => [str_repeat('a', 256)],
]);

it('solo reasigna casos EN SEGUIMIENTO', function (string $status): void {
    ['case' => $case, 'user' => $user] = followUpFixture();
    DB::table('telemedicine_cases')->where('id', $case->id)->update(['status' => $status]);

    expect(TelemedicineCaseFollowUpRescheduler::caseCanBeRescheduled($case->fresh()))->toBeFalse()
        ->and(fn () => TelemedicineCaseFollowUpRescheduler::reschedule($case->fresh(), 60, 'Observación válida', $user))
        ->toThrow(DomainException::class, 'Solo se puede reasignar el seguimiento de un caso EN SEGUIMIENTO.');
})->with(['ASIGNADO', 'ALTA MEDICA']);

it('no deja reasignar a un médico de otro equipo ni a un usuario sin médico', function (): void {
    ['case' => $case] = followUpFixture();

    $otherTeamUser = User::query()
        ->whereIn('doctor_id', TelemedicineDoctor::query()->whereNotNull('supplier_id')->select('id'))
        ->first();

    if ($otherTeamUser !== null) {
        expect(fn () => TelemedicineCaseFollowUpRescheduler::reschedule($case, 60, 'Observación válida', $otherTeamUser))
            ->toThrow(DomainException::class, 'El caso no pertenece a su equipo médico.');
    }

    expect(fn () => TelemedicineCaseFollowUpRescheduler::reschedule($case, 60, 'Observación válida', new User))
        ->toThrow(DomainException::class, 'El caso no pertenece a su equipo médico.')
        ->and(fn () => TelemedicineCaseFollowUpRescheduler::reschedule($case, 60, 'Observación válida', null))
        ->toThrow(DomainException::class, 'El caso no pertenece a su equipo médico.');
});

it('la acción del escritorio solo aparece en casos EN SEGUIMIENTO y pide la lista y la observación', function (): void {
    $widget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Widgets/TelemedicineCaseTableDash.php');

    $start = (int) strpos($widget, "Action::make('rescheduleFollowUp')");
    $action = substr($widget, $start, (int) strpos($widget, 'RegenerateTelemedicineCaseDocumentsAction::make(', $start) - $start);

    expect($start)->toBeGreaterThan(0)
        ->and($action)
        ->toContain("->label('Reasignar seguimiento')")
        ->toContain('TelemedicineCaseFollowUpRescheduler::caseCanBeRescheduled($record)')
        ->toContain('->options(TelemedicineCaseFollowUpSchedule::OPTIONS)')
        ->toContain("Textarea::make('observation')")
        ->toContain('->required()')
        ->toContain('$this->guardDashboardCaseInteraction($record)')
        ->toContain('TelemedicineCaseFollowUpRescheduler::reschedule(');
});

it('la consulta inicial ofrece 24 horas en «Próximo Seguimiento»', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php');

    expect(substr_count($form, "24 => '24 horas',"))->toBe(2)
        ->and(TelemedicineCaseFollowUpSchedule::minutesFor(24))->toBe(1440);
});

it('muestra el próximo seguimiento en minutos u horas según el valor guardado', function (mixed $value, string $label): void {
    expect(TelemedicineCaseFollowUpSchedule::optionLabel($value))->toBe($label);
})->with([
    [30, '30 minutos'],
    [180, '180 minutos'],
    [24, '24 horas'],
    ['72', '72 horas'],
    [45, '45 minutos'],
    [null, '—'],
    [0, '—'],
    ['abc', '—'],
]);

it('las fichas de la consulta ya no fuerzan el sufijo «minutos»', function (string $path): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);

    expect($source)
        ->not->toContain("->suffix(' minutos')")
        ->toContain('TelemedicineCaseFollowUpSchedule::optionLabel($state)');
})->with([
    'telemedicina' => ['app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientInfolist.php'],
    'operaciones' => ['app/Filament/Operations/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientInfolist.php'],
]);
