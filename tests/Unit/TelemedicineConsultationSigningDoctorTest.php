<?php

declare(strict_types=1);

use App\Models\TelemedicineCase;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Support\Telemedicine\ConsultationCreateWizardDefaults;
use App\Support\Telemedicine\TelemedicineConsultationSigningDoctor;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Estos tests escriben, así que corren dentro de una transacción que siempre se
 * revierte, igual que PlanStructurePersistenceTest. No se deja nada en la base.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    TelemedicineConsultationSigningDoctor::flush();
});

afterEach(function (): void {
    DB::rollBack();
    TelemedicineConsultationSigningDoctor::flush();
});

function crearMedicoDeTelemedicinaDePrueba(string $nombre): TelemedicineDoctor
{
    return TelemedicineDoctor::query()->create([
        'full_name' => $nombre,
        'nro_identificacion' => (string) random_int(10000000, 99999999),
        'email' => 'pest-'.uniqid().'@tudrencasa.com',
        'code_mpps' => (string) random_int(100000, 999999),
        'status' => 'ACTIVO',
        'managed_by' => 'TDG',
        'signature' => 'firmas-medicos/pest-'.uniqid().'.png',
    ]);
}

function crearUsuarioMedicoDePrueba(?int $doctorId): User
{
    return User::query()->create([
        'name' => 'PEST MEDICO',
        'email' => 'pest-'.uniqid().'@tudrencasa.com',
        'password' => bcrypt('secret-pest'),
        'doctor_id' => $doctorId,
        'status' => 'ACTIVO',
    ]);
}

it('firma con el médico del usuario que atiende, no con el asignado al caso', function (): void {
    $doctorQueAtiende = crearMedicoDeTelemedicinaDePrueba('DOCTOR A QUE ATIENDE');
    $doctorAsignadoAlCaso = crearMedicoDeTelemedicinaDePrueba('DOCTOR B ASIGNADO');
    $usuario = crearUsuarioMedicoDePrueba((int) $doctorQueAtiende->id);

    $caso = new TelemedicineCase;
    $caso->telemedicine_doctor_id = $doctorAsignadoAlCaso->id;

    expect(TelemedicineConsultationSigningDoctor::idForUser($usuario))
        ->toBe((int) $doctorQueAtiende->id)
        ->and(TelemedicineConsultationSigningDoctor::defaultIdForForm($usuario, $caso))
        ->toBe((int) $doctorQueAtiende->id)
        ->and(TelemedicineConsultationSigningDoctor::defaultIdForForm($usuario, $caso))
        ->not->toBe((int) $doctorAsignadoAlCaso->id);
});

it('devuelve la ficha completa del médico firmante, con su sello', function (): void {
    $doctorQueAtiende = crearMedicoDeTelemedicinaDePrueba('DOCTOR CON SELLO');
    $usuario = crearUsuarioMedicoDePrueba((int) $doctorQueAtiende->id);

    $firmante = TelemedicineConsultationSigningDoctor::forUser($usuario);

    expect($firmante)->toBeInstanceOf(TelemedicineDoctor::class)
        ->and((int) $firmante->id)->toBe((int) $doctorQueAtiende->id)
        ->and($firmante->signature)->toBe($doctorQueAtiende->signature);
});

it('no resuelve firmante si el usuario no tiene médico vinculado', function (): void {
    $usuario = crearUsuarioMedicoDePrueba(null);

    expect(TelemedicineConsultationSigningDoctor::idForUser($usuario))->toBeNull()
        ->and(TelemedicineConsultationSigningDoctor::forUser($usuario))->toBeNull();
});

it('no resuelve firmante si el vínculo apunta a una ficha de médico inexistente', function (): void {
    $inexistente = ((int) TelemedicineDoctor::query()->max('id')) + 5000;
    $usuario = crearUsuarioMedicoDePrueba($inexistente);

    expect(TelemedicineConsultationSigningDoctor::idForUser($usuario))->toBeNull();
});

it('solo cae al médico del caso cuando el usuario no tiene ficha vinculada', function (): void {
    $doctorAsignadoAlCaso = crearMedicoDeTelemedicinaDePrueba('DOCTOR B ASIGNADO');
    $usuarioSinFicha = crearUsuarioMedicoDePrueba(null);

    $caso = new TelemedicineCase;
    $caso->telemedicine_doctor_id = $doctorAsignadoAlCaso->id;

    expect(TelemedicineConsultationSigningDoctor::defaultIdForForm($usuarioSinFicha, $caso))
        ->toBe((int) $doctorAsignadoAlCaso->id)
        ->and(TelemedicineConsultationSigningDoctor::defaultIdForForm($usuarioSinFicha, null))
        ->toBeNull();
});

it('el asistente de consulta arranca con el firmante del usuario que atiende', function (): void {
    $doctorQueAtiende = crearMedicoDeTelemedicinaDePrueba('DOCTOR A QUE ATIENDE');
    $doctorAsignadoAlCaso = crearMedicoDeTelemedicinaDePrueba('DOCTOR B ASIGNADO');
    $usuario = crearUsuarioMedicoDePrueba((int) $doctorQueAtiende->id);

    $caso = new TelemedicineCase([
        'telemedicine_patient_id' => 20,
        'telemedicine_doctor_id' => $doctorAsignadoAlCaso->id,
        'code' => 'CASO-FIRMA',
    ]);
    $caso->id = 10;

    $paciente = new TelemedicinePatient([
        'full_name' => 'Paciente de prueba',
        'nro_identificacion' => '12345678',
    ]);

    $state = ConsultationCreateWizardDefaults::formStatePatientStepFromCaseAndPatient(
        $caso,
        $paciente,
        (int) $usuario->id,
        2,
    );

    expect($state['status'])->toBe('EN SEGUIMIENTO')
        ->and($state['assigned_by'])->toBe((int) $usuario->id)
        ->and($state['telemedicine_doctor_id'])->toBe((int) $doctorQueAtiende->id)
        ->and($state['telemedicine_doctor_id'])->not->toBe((int) $doctorAsignadoAlCaso->id);
});

it('no acepta como firmante a quien no es un usuario del sistema', function (): void {
    expect(TelemedicineConsultationSigningDoctor::idForUser(null))->toBeNull()
        ->and(TelemedicineConsultationSigningDoctor::idForUser('7'))->toBeNull()
        ->and(TelemedicineConsultationSigningDoctor::idForUserId(0))->toBeNull()
        ->and(TelemedicineConsultationSigningDoctor::idForUserId(null))->toBeNull();
});
