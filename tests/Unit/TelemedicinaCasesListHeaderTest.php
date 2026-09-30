<?php

declare(strict_types=1);

use App\Filament\Telemedicina\Resources\TelemedicineCases\Pages\ListTelemedicineCases;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicinePatient;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(function (): void {
    Auth::forgetUser();
    DB::rollBack();
});

it('resume los casos con el mismo alcance que la tabla: sin altas, eliminados ni casos de otro médico', function (): void {
    $doctor = TelemedicineDoctor::query()->where('managed_by', '!=', 'TDG')->orWhereNull('managed_by')->orderBy('id')->first()
        ?? TelemedicineDoctor::query()->orderBy('id')->firstOrFail();
    $otherDoctorId = TelemedicineDoctor::query()->whereKeyNot($doctor->id)->value('id');
    $patientId = TelemedicinePatient::query()->value('id');
    $urgentId = DB::table('telemedicine_priorities')->where('name', 'Emergencia')->value('id');
    $standardId = DB::table('telemedicine_priorities')->where('name', 'Estándar')->value('id');

    Auth::setUser(User::factory()->make(['id' => 999998, 'departament' => ['TELEMEDICINA'], 'doctor_id' => $doctor->id]));

    $before = ListTelemedicineCases::summary();

    $case = fn (int $doctorId, string $status, ?int $priorityId, string $code, ?string $createdAt = null): int => DB::table('telemedicine_cases')->insertGetId([
        'telemedicine_patient_id' => $patientId, 'telemedicine_doctor_id' => $doctorId, 'telemedicine_priority_id' => $priorityId,
        'code' => $code, 'status' => $status, 'managed_by' => $doctor->managed_by,
        'created_at' => $createdAt ?? now(), 'updated_at' => now(),
    ]);

    $case($doctor->id, 'ASIGNADO', $urgentId, 'ZZLST-1');
    $case($doctor->id, 'EN SEGUIMIENTO', $standardId, 'ZZLST-2', now()->subDays(3)->toDateTimeString());
    $case($doctor->id, 'ALTA MEDICA', $urgentId, 'ZZLST-3');
    $case($doctor->id, 'ELIMINADO', $urgentId, 'ZZLST-4');
    $case($otherDoctorId, 'ASIGNADO', $urgentId, 'ZZLST-5');

    $after = ListTelemedicineCases::summary();

    expect($after['total'] - $before['total'])->toBe(2)
        ->and($after['assigned'] - $before['assigned'])->toBe(1)
        ->and($after['follow_up'] - $before['follow_up'])->toBe(1)
        ->and($after['urgent'] - $before['urgent'])->toBe(1)
        ->and($after['today'] - $before['today'])->toBe(1);
})->skip(fn (): bool => DB::table('telemedicine_priorities')->where('name', 'Emergencia')->doesntExist(), 'Sin catálogo de prioridades');

it('el encabezado muestra el total, los indicadores y el alcance del usuario', function (): void {
    $html = view('filament.telemedicina.cases.list-header', [
        'scopeLabel' => 'Casos de médicos TDG',
        'summary' => ['total' => 12, 'assigned' => 3, 'follow_up' => 8, 'urgent' => 2, 'today' => 1],
    ])->render();

    expect($html)
        ->toContain('Casos de médicos TDG')
        ->toContain('Gestión de casos')
        ->toContain('Por atender')
        ->toContain('En seguimiento')
        ->toContain('Urgentes')
        ->toContain('Nuevos hoy')
        ->toContain('>12<');
});

it('sin casos no muestra indicadores y el nombre del recurso ya no tiene la tilde errada', function (): void {
    $html = view('filament.telemedicina.cases.list-header', ['summary' => []])->render();
    $resource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineCases/TelemedicineCaseResource.php');

    expect($html)->toContain('Sus casos asignados')->not->toContain('Por atender')
        ->and($resource)->not->toContain('Telemedicína');
});
