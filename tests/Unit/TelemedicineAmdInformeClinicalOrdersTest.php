<?php

declare(strict_types=1);

use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDoctor;
use App\Models\User;
use App\Support\Telemedicine\TelemedicineInformeClinicalOrders;
use App\Support\Telemedicine\TelemedicineInformeLargoDataBuilder;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Caso 54905-01277 (25/10/2026): el Informe AMD rehacía el Informe Médico sin
 * las indicaciones del médico y el PDF decía «Sin consulta con especialistas»
 * aunque la consulta tenía PSIQUIATRA. Escribe dentro de una transacción que
 * siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    $this->actingAs(User::query()->orderBy('id')->firstOrFail());
});

afterEach(fn () => DB::rollBack());

/**
 * Consulta real sin indicaciones en sus tablas, para sembrarle las propias.
 */
function consultaSinIndicaciones(): TelemedicineConsultationPatient
{
    $consulta = TelemedicineConsultationPatient::query()
        ->whereNotNull('telemedicine_case_id')
        ->whereNotNull('telemedicine_patient_id')
        ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('telemedicine_patient_specialties')->whereColumn('telemedicine_patient_specialties.telemedicine_consultation_patient_id', 'telemedicine_consultation_patients.id'))
        ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('telemedicine_patient_labs')->whereColumn('telemedicine_patient_labs.telemedicine_consultation_patient_id', 'telemedicine_consultation_patients.id'))
        ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('telemedicine_patient_medications')->whereColumn('telemedicine_patient_medications.telemedicine_consultation_patient_id', 'telemedicine_consultation_patients.id'))
        ->latest('id')
        ->first();

    if ($consulta === null) {
        test()->markTestSkipped('No hay una consulta sin indicaciones en esta base.');
    }

    return $consulta;
}

function sembrarIndicaciones(TelemedicineConsultationPatient $consulta): void
{
    $base = [
        'telemedicine_patient_id' => $consulta->telemedicine_patient_id,
        'telemedicine_case_id' => $consulta->telemedicine_case_id,
        'telemedicine_consultation_patient_id' => $consulta->id,
        'created_at' => now(),
        'updated_at' => now(),
    ];

    DB::table('telemedicine_patient_specialties')->insert($base + ['specialty' => 'PSIQUIATRA', 'type' => 'CUBIERTO', 'assigned_by' => '1', 'status' => 'PENDIENTE']);
    DB::table('telemedicine_patient_labs')->insert($base + ['laboratory' => 'HEMATOLOGIA COMPLETA', 'type' => 'CUBIERTO', 'status' => 'PENDIENTE']);
    DB::table('telemedicine_patient_medications')->insert($base + ['medicine' => 'LOSARTAN 50 MG', 'indications' => '1 TAB CADA 12 HORAS', 'status' => 'PENDIENTE']);
}

/**
 * Lo que hacen las dos llamadas del Informe AMD: contexto + médico + datos clínicos, sin listas.
 *
 * @return array<string, mixed>
 */
function informeComoLoArmaAmd(TelemedicineConsultationPatient $consulta): array
{
    return TelemedicineInformeLargoDataBuilder::buildFromContext(
        [...$consulta->toArray(), 'telemedicine_consultation_id' => $consulta->id],
        new TelemedicineDoctor(['full_name' => 'DR. QA']),
        ['reason_consultation' => 'CONTROL'],
    );
}

it('el informe que rehace el flujo AMD conserva especialistas, laboratorios y medicamentos de la consulta', function (): void {
    $consulta = consultaSinIndicaciones();
    sembrarIndicaciones($consulta);

    $orders = TelemedicineInformeClinicalOrders::forReport(informeComoLoArmaAmd($consulta));

    expect($orders['specialists'])->toBe(['PSIQUIATRA'])
        ->and($orders['labs'])->toBe(['HEMATOLOGIA COMPLETA'])
        ->and($orders['medications'])->toBe([['medicines' => 'LOSARTAN 50 MG', 'indications' => '1 TAB CADA 12 HORAS']]);
});

it('el PDF del informe imprime el especialista en vez de «Sin consulta con especialistas»', function (): void {
    $consulta = consultaSinIndicaciones();
    sembrarIndicaciones($consulta);

    $html = view('documents.partials.informe-medico-homologado', ['data' => informeComoLoArmaAmd($consulta), 'variant' => 'largo'])->render();

    expect($html)->toContain('PSIQUIATRA')
        ->toContain('HEMATOLOGIA COMPLETA')
        ->toContain('LOSARTAN 50 MG')
        ->not->toContain('Sin consulta con especialistas.')
        ->not->toContain('Sin medicamentos indicados.');
});

it('sin filas en las tablas toma las listas guardadas en la consulta (consultas anteriores)', function (): void {
    $consulta = consultaSinIndicaciones();
    DB::table('telemedicine_consultation_patients')->where('id', $consulta->id)->update([
        'consult_specialist' => json_encode(['CARDIOLOGO']),
        'other_specialist' => json_encode(['PSICOLOGO']),
    ]);

    $orders = TelemedicineInformeClinicalOrders::forReport(informeComoLoArmaAmd($consulta->fresh()));

    expect($orders['specialists'])->toBe(['CARDIOLOGO', 'PSICOLOGO']);
});

it('una lista pasada explícitamente se respeta, aunque esté vacía', function (): void {
    $consulta = consultaSinIndicaciones();
    sembrarIndicaciones($consulta);

    $informe = TelemedicineInformeLargoDataBuilder::buildFromContext(
        [...$consulta->toArray(), 'telemedicine_consultation_id' => $consulta->id],
        new TelemedicineDoctor(['full_name' => 'DR. QA']),
        [],
        consultSpecialistArr: [],
        otherSpecialistArr: [],
    );

    expect($informe['consultSpecialistArr'])->toBe([])
        ->and($informe['otherSpecialistArr'])->toBe([])
        ->and($informe['labsArr'])->toBe(['HEMATOLOGIA COMPLETA']);
});

it('el Informe AMD pendiente (sin consulta guardada) sale sin indicaciones y sin consultar la base', function (): void {
    $consultas = 0;
    DB::listen(function () use (&$consultas): void {
        $consultas++;
    });

    $informe = TelemedicineInformeLargoDataBuilder::buildFromContext(
        ['telemedicine_consultation_id' => 0, 'telemedicine_case_id' => 0, 'telemedicine_patient_id' => 0],
        new TelemedicineDoctor(['full_name' => 'DR. QA']),
    );

    expect($informe['consultSpecialistArr'])->toBe([])
        ->and($informe['medicationsArr'])->toBe([])
        ->and($consultas)->toBe(0);
});
