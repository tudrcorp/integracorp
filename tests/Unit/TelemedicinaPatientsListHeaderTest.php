<?php

declare(strict_types=1);

use App\Filament\Telemedicina\Resources\TelemedicinePatients\Pages\ListTelemedicinePatients;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicinePatient;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

it('resume los casos del equipo de guardia del médico con el mismo alcance que la tabla de pacientes', function (): void {
    [$doctorId, $colleagueDoctorId] = TelemedicineDoctor::query()->where('managed_by', 'TDG')->orderBy('id')->limit(2)->pluck('id')->all();
    $otherTeamDoctorId = TelemedicineDoctor::query()->whereNotNull('supplier_id')->orderBy('id')->value('id');
    [$patientA, $patientB] = TelemedicinePatient::query()->orderBy('id')->limit(2)->pluck('id')->all();

    $before = ListTelemedicinePatients::summary($doctorId);

    $now = now();
    $case = fn (int $doctor, int $patient, string $status, string $code, string $managedBy = 'TDG'): int => DB::table('telemedicine_cases')->insertGetId([
        'telemedicine_patient_id' => $patient, 'telemedicine_doctor_id' => $doctor, 'code' => $code, 'status' => $status,
        'managed_by' => $managedBy, 'created_at' => $now, 'updated_at' => $now,
    ]);

    $case($doctorId, $patientA, 'ASIGNADO', 'ZZHDR-1');
    $case($doctorId, $patientA, 'EN SEGUIMIENTO', 'ZZHDR-2');
    $case($doctorId, $patientB, 'ALTA MEDICA', 'ZZHDR-3');
    $case($doctorId, $patientB, 'PACIENTE DE ALTA', 'ZZHDR-4');
    $case($doctorId, $patientB, 'ELIMINADO', 'ZZHDR-5');
    $case($colleagueDoctorId, $patientA, 'ASIGNADO', 'ZZHDR-6');
    $case($otherTeamDoctorId, $patientA, 'ASIGNADO', 'ZZHDR-7', 'PROVEEDOR');

    $after = ListTelemedicinePatients::summary($doctorId);

    expect($after['assigned'] - $before['assigned'])->toBe(2)
        ->and($after['follow_up'] - $before['follow_up'])->toBe(1)
        ->and($after['discharged'] - $before['discharged'])->toBe(1)
        ->and($after['patients'])->toBeGreaterThanOrEqual(2)
        ->and($after['patients'] - $before['patients'])->toBeLessThanOrEqual(2);
});

it('sin médico vinculado devuelve ceros y el encabezado lo explica', function (): void {
    expect(ListTelemedicinePatients::summary(null))->toBe(['patients' => 0, 'assigned' => 0, 'follow_up' => 0, 'discharged' => 0]);

    $html = view('filament.telemedicina.patients.list-header', ['linked' => false, 'summary' => []])->render();

    expect($html)
        ->toContain('Ficha del paciente')
        ->toContain('no tiene un médico asociado')
        ->not->toContain('Por atender');
});

it('el encabezado muestra el total de pacientes y los indicadores de casos', function (): void {
    $html = view('filament.telemedicina.patients.list-header', [
        'linked' => true,
        'summary' => ['patients' => 7, 'assigned' => 2, 'follow_up' => 4, 'discharged' => 1],
    ])->render();

    expect($html)
        ->toContain('Gestión telemédica')
        ->toContain('Ficha del paciente')
        ->toContain('Por atender')
        ->toContain('En seguimiento')
        ->toContain('Alta médica')
        ->toContain('>7<');
});
