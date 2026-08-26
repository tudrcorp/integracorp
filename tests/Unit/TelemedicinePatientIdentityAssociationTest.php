<?php

declare(strict_types=1);

use App\Models\TelemedicinePatient;
use App\Support\Telemedicine\TelemedicinePatientAssociationResolver;
use App\Support\Telemedicine\TelemedicinePatientIdentity;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
    ]);

    DB::purge('sqlite');
    DB::reconnect('sqlite');

    Schema::dropIfExists('telemedicine_patients');
    Schema::create('telemedicine_patients', function (Blueprint $table): void {
        $table->id();
        $table->string('full_name')->nullable();
        $table->string('nro_identificacion')->nullable();
        $table->string('email')->nullable();
        $table->string('phone')->nullable();
        $table->string('birth_date')->nullable();
        $table->string('sex')->nullable();
        $table->string('age')->nullable();
        $table->string('address')->nullable();
        $table->unsignedBigInteger('afilliation_corporate_id')->nullable();
        $table->string('created_by')->nullable();
        $table->timestamps();
    });
});

it('normaliza cédulas quitando espacios y puntos', function (): void {
    expect(TelemedicinePatientIdentity::normalizeDocument(' 16.242.686 '))
        ->toBe('16242686')
        ->and(TelemedicinePatientIdentity::documentsMatch('M-16242686', 'm-16242686'))
        ->toBeTrue();
});

it('normaliza alias de sexo a MASCULINO o FEMENINO', function (): void {
    expect(TelemedicinePatientIdentity::normalizeSex('m'))->toBe('MASCULINO')
        ->and(TelemedicinePatientIdentity::normalizeSex('F'))->toBe('FEMENINO')
        ->and(TelemedicinePatientIdentity::normalizeSex('femenino'))->toBe('FEMENINO')
        ->and(TelemedicinePatientIdentity::normalizeSex(null))->toBeNull()
        ->and(TelemedicinePatientIdentity::normalizeSex('  '))->toBeNull()
        ->and(TelemedicinePatientIdentity::isCanonicalSex('M'))->toBeTrue()
        ->and(TelemedicinePatientIdentity::needsSexPrompt(null))->toBeTrue()
        ->and(TelemedicinePatientIdentity::needsSexPrompt('NO ESPECIFICADO'))->toBeTrue()
        ->and(TelemedicinePatientIdentity::needsSexPrompt('FEMENINO'))->toBeFalse();
});

it('persiste el sexo canónico en el padrón solo cuando falta', function (): void {
    $record = new class
    {
        public mixed $sex = null;

        public function forceFill(array $attributes): self
        {
            $this->sex = $attributes['sex'] ?? $this->sex;

            return $this;
        }

        public function save(): bool
        {
            return true;
        }
    };

    TelemedicinePatientIdentity::persistCanonicalSexIfSourceMissing($record, 'FEMENINO');
    expect($record->sex)->toBe('FEMENINO');

    TelemedicinePatientIdentity::persistCanonicalSexIfSourceMissing($record, 'MASCULINO');
    expect($record->sex)->toBe('FEMENINO');
});

it('asocia familiares con el mismo email como pacientes distintos por cédula', function (): void {
    $ana = TelemedicinePatientAssociationResolver::upsertByDocument([
        'full_name' => 'Ana Caren Sotillo Jerez',
        'nro_identificacion' => '16242686',
        'email' => 'asotillo@semitech.com.ve',
        'phone' => '0424-4964601',
        'sex' => 'FEMENINO',
        'created_by' => 'Tester',
    ]);

    $barta = TelemedicinePatientAssociationResolver::upsertByDocument([
        'full_name' => 'BARTA DEL CARMEN JEREZ DUARTE',
        'nro_identificacion' => '4128740',
        'email' => 'asotillo@semitech.com.ve',
        'phone' => '416-6483741',
        'sex' => 'F',
        'created_by' => 'Tester',
    ]);

    expect($ana['was_recently_created'])->toBeTrue()
        ->and($barta['was_recently_created'])->toBeTrue()
        ->and($ana['patient']->id)->not->toBe($barta['patient']->id)
        ->and(TelemedicinePatient::query()->count())->toBe(2)
        ->and($ana['patient']->fresh()->nro_identificacion)->toBe('16242686')
        ->and($barta['patient']->fresh()->nro_identificacion)->toBe('4128740')
        ->and($barta['patient']->fresh()->sex)->toBe('FEMENINO');
});

it('actualiza el mismo paciente al reasociar la misma cédula sin crear duplicado', function (): void {
    $first = TelemedicinePatientAssociationResolver::upsertByDocument([
        'full_name' => 'Ana Caren Sotillo Jerez',
        'nro_identificacion' => '16242686',
        'email' => 'asotillo@semitech.com.ve',
        'phone' => '0424-0000000',
        'sex' => 'M',
    ]);

    $second = TelemedicinePatientAssociationResolver::upsertByDocument([
        'full_name' => 'Ana Caren Sotillo Jerez',
        'nro_identificacion' => '16.242.686',
        'email' => 'asotillo@semitech.com.ve',
        'phone' => '0424-4964601',
        'sex' => null,
    ]);

    expect($second['was_recently_created'])->toBeFalse()
        ->and($second['patient']->id)->toBe($first['patient']->id)
        ->and($second['patient']->phone)->toBe('0424-4964601')
        ->and($second['patient']->sex)->toBe('MASCULINO')
        ->and(TelemedicinePatient::query()->count())->toBe(1);
});

it('rechaza el alta de paciente sin sexo para no violar el NOT NULL de producción', function (): void {
    expect(fn () => TelemedicinePatientAssociationResolver::upsertByDocument([
        'full_name' => 'Ana',
        'nro_identificacion' => '16242686',
        'email' => 'ana@example.com',
        'sex' => null,
    ]))->toThrow(ValidationException::class);
});

it('exige cédula para asociar y no permite matchear solo por email', function (): void {
    TelemedicinePatientAssociationResolver::upsertByDocument([
        'full_name' => 'Ana',
        'nro_identificacion' => '16242686',
        'email' => 'shared@example.com',
        'sex' => 'FEMENINO',
    ]);

    expect(fn () => TelemedicinePatientAssociationResolver::upsertByDocument([
        'full_name' => 'Barta',
        'nro_identificacion' => '',
        'email' => 'shared@example.com',
    ]))->toThrow(ValidationException::class);
});

it('enforceConsultationIdentity fuerza nombre y cédula del paciente FK', function (): void {
    $patient = TelemedicinePatient::query()->create([
        'full_name' => 'Ana Caren Sotillo Jerez',
        'nro_identificacion' => '16242686',
        'sex' => 'FEMENINO',
        'age' => 42,
    ]);

    $enforced = TelemedicinePatientIdentity::enforceConsultationIdentity([
        'telemedicine_patient_id' => 999,
        'full_name' => 'BARTA DEL CARMEN JEREZ DUARTE',
        'nro_identificacion' => '4128740',
        'sex' => 'FEMENINO',
        'age' => 74,
    ], $patient);

    expect($enforced['telemedicine_patient_id'])->toBe($patient->id)
        ->and($enforced['full_name'])->toBe('Ana Caren Sotillo Jerez')
        ->and($enforced['nro_identificacion'])->toBe('16242686')
        ->and($enforced['age'])->toBe(42);
});
