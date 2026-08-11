<?php

declare(strict_types=1);

use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Support\Telemedicine\TelemedicineCaseFactory;
use App\Support\Telemedicine\TelemedicineCaseIdentity;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

function createTelemedicineIdentityTables(): void
{
    Schema::dropIfExists('telemedicine_cases');
    Schema::dropIfExists('telemedicine_patients');

    Schema::create('telemedicine_patients', function (Blueprint $table): void {
        $table->id();
        $table->string('full_name')->nullable();
        $table->string('nro_identificacion')->nullable();
        $table->string('birth_date')->nullable();
        $table->string('sex')->nullable();
        $table->string('age')->nullable();
        $table->string('phone')->nullable();
        $table->string('address')->nullable();
        $table->unsignedBigInteger('country_id')->nullable();
        $table->unsignedBigInteger('state_id')->nullable();
        $table->unsignedBigInteger('city_id')->nullable();
        $table->timestamps();
    });

    Schema::create('telemedicine_cases', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('telemedicine_patient_id')->nullable();
        $table->unsignedBigInteger('telemedicine_doctor_id')->nullable();
        $table->string('patient_name')->nullable();
        $table->string('patient_age')->nullable();
        $table->string('patient_sex')->nullable();
        $table->string('patient_phone')->nullable();
        $table->string('patient_phone_2')->nullable();
        $table->string('patient_address')->nullable();
        $table->unsignedBigInteger('patient_country_id')->nullable();
        $table->unsignedBigInteger('patient_state_id')->nullable();
        $table->unsignedBigInteger('patient_city_id')->nullable();
        $table->string('status')->nullable();
        $table->string('code')->nullable();
        $table->string('reason')->nullable();
        $table->string('assigned_by')->nullable();
        $table->string('managed_by')->nullable();
        $table->unsignedBigInteger('supplier_id')->nullable();
        $table->timestamps();
    });
}

beforeEach(function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
    ]);

    DB::purge('sqlite');
    DB::reconnect('sqlite');

    createTelemedicineIdentityTables();
});

it('normaliza y compara nombres de paciente sin importar mayúsculas ni espacios', function (): void {
    expect(TelemedicineCaseIdentity::namesMatch('  Estrella Troconiz ', 'estrella troconiz'))->toBeTrue()
        ->and(TelemedicineCaseIdentity::namesMatch('NODA EDUARD', 'TROCONIZ ESTRELLA'))->toBeFalse();
});

it('crea un caso con patient_name igual al full_name del paciente', function (): void {
    $patient = TelemedicinePatient::query()->create([
        'full_name' => 'TROCONIZ ESTRELLA',
        'nro_identificacion' => 'V123',
        'age' => '40',
        'sex' => 'F',
        'phone' => '04141234567',
        'address' => 'Caracas',
        'country_id' => 1,
        'state_id' => 2,
        'city_id' => 3,
    ]);

    $case = TelemedicineCaseFactory::createForPatient($patient, [
        'reason' => 'Consulta',
        'assigned_by' => 'Tester',
        'patient_phone' => '04240001122',
        'patient_address' => 'Dirección alternativa',
    ]);

    expect($case->telemedicine_patient_id)->toBe($patient->id)
        ->and($case->patient_name)->toBe('TROCONIZ ESTRELLA')
        ->and($case->patient_age)->toBe('40')
        ->and($case->patient_sex)->toBe('F')
        ->and($case->patient_phone)->toBe('04240001122')
        ->and($case->patient_address)->toBe('Dirección alternativa');
});

it('corrige patient_name divergente al guardar el caso', function (): void {
    $patient = TelemedicinePatient::query()->create([
        'full_name' => 'TROCONIZ ESTRELLA',
        'age' => '40',
        'sex' => 'F',
    ]);

    $case = TelemedicineCase::query()->create([
        'telemedicine_patient_id' => $patient->id,
        'patient_name' => 'NODA EDUARD',
        'status' => 'ASIGNADO',
        'code' => 'TEST-1',
    ]);

    expect($case->fresh()->patient_name)->toBe('TROCONIZ ESTRELLA');
});

it('sincroniza patient_name de los casos al editar el full_name del paciente', function (): void {
    $patient = TelemedicinePatient::query()->create([
        'full_name' => 'TROCONIZ ESTRELLA',
        'age' => '40',
        'sex' => 'F',
    ]);

    $case = TelemedicineCaseFactory::createForPatient($patient, [
        'reason' => 'Consulta',
        'code' => 'SYNC-1',
    ]);

    $patient->update([
        'full_name' => 'TROCONIZ ESTRELLA MARIA',
        'age' => '41',
    ]);

    expect($case->fresh()->patient_name)->toBe('TROCONIZ ESTRELLA MARIA')
        ->and($case->fresh()->patient_age)->toBe('41');
});

it('usa identidad del paciente FK en coordinación y no el nombre de consulta ajeno', function (): void {
    $identity = TelemedicineCaseIdentity::coordinationIdentity(
        [
            'full_name' => 'NODA EDUARD',
            'telemedicine_case_id' => 99,
        ],
        [
            'id' => 8,
            'full_name' => 'TROCONIZ ESTRELLA',
            'nro_identificacion' => 'V12345678',
            'birth_date' => '1985-01-01',
            'age' => '40',
        ],
    );

    expect($identity['patient'])->toBe('TROCONIZ ESTRELLA')
        ->and($identity['ci_patient'])->toBe('V12345678')
        ->and($identity['age_patient'])->toBe('40')
        ->and($identity['relationship_patient'])->toBe('TITULAR');
});

it('preserva el parentesco de la consulta y usa TITULAR si no viene', function (): void {
    $withRelationship = TelemedicineCaseIdentity::coordinationIdentity(
        [
            'relationship_patient' => 'HIJO',
        ],
        [
            'id' => 9,
            'full_name' => 'PACIENTE HIJO',
            'nro_identificacion' => 'V999',
        ],
    );

    $withoutRelationship = TelemedicineCaseIdentity::coordinationIdentity(
        [],
        [
            'id' => 10,
            'full_name' => 'PACIENTE SIN PARENTESCO',
            'nro_identificacion' => '',
        ],
    );

    expect($withRelationship['relationship_patient'])->toBe('HIJO')
        ->and($withoutRelationship['relationship_patient'])->toBe('TITULAR')
        ->and($withoutRelationship['ci_patient'])->toBe('NO ESPECIFICADO')
        ->and($withoutRelationship['patient'])->toBe('PACIENTE SIN PARENTESCO');
});

it('el comando de auditoría detecta inconsistencias históricas', function (): void {
    $patient = TelemedicinePatient::query()->create([
        'full_name' => 'TROCONIZ ESTRELLA',
        'nro_identificacion' => 'V123',
        'age' => '40',
        'sex' => 'F',
    ]);

    // Inserción directa para simular histórico inconsistente (sin observer).
    DB::table('telemedicine_cases')->insert([
        'telemedicine_patient_id' => $patient->id,
        'patient_name' => 'NODA EDUARD',
        'status' => 'ASIGNADO',
        'code' => 'AUDIT-1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('telemedicine_cases')->insert([
        'telemedicine_patient_id' => $patient->id,
        'patient_name' => 'TROCONIZ ESTRELLA',
        'status' => 'ASIGNADO',
        'code' => 'AUDIT-2',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Artisan::call('telemedicine:audit-patient-case-identity');

    $output = Artisan::output();

    expect($output)
        ->toContain('Se encontraron 1 inconsistencia')
        ->toContain('NODA EDUARD')
        ->toContain('TROCONIZ ESTRELLA')
        ->toContain('AUDIT-1')
        ->toContain('telemedicine:remediate-shared-email-patient-identity')
        ->not->toContain('AUDIT-2');
});
