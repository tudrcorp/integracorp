<?php

declare(strict_types=1);

use App\Http\Controllers\ClinicalRecordPdfController;
use App\Jobs\GenerateClinicalRecordPdfJob;
use App\Models\ClinicalRecordAccessLog;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineHistoryPatient;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\User;
use App\Support\Telemedicine\ClinicalRecord\ClinicalRecordAccess;
use App\Support\Telemedicine\ClinicalRecord\ClinicalRecordPdf;
use App\Support\Telemedicine\ClinicalRecord\PatientClinicalRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

/**
 * Ficha clínica del paciente (Telemedicina y Operaciones). Escribe dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();

    $this->user = function (array $departments, array $extra = []): User {
        $user = (new User)->forceFill(['name' => 'Ana Pérez', 'email' => 'qa@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => $departments, ...$extra]);
        $user->id = random_int(900000000, 909999999);

        return $user;
    };

    $this->patient = TelemedicinePatient::query()->forceCreate([
        'full_name' => 'MARIA JOSE QA RIVAS',
        'nro_identificacion' => (string) random_int(30000000, 39999999),
        'birth_date' => '6/8/1978',
        'sex' => 'FEMENINO',
        'phone' => '04140000000',
        'type' => 'QA',
    ]);
});

afterEach(function (): void {
    DB::rollBack();
});

it('arma la ficha con lo registrado y se actualiza sola al agregar datos', function (): void {
    $empty = PatientClinicalRecord::build($this->patient);

    expect($empty['problems'])->toBe([])
        ->and($empty['medications'])->toBe([])
        ->and($empty['patient']['birth'])->toBe('06/08/1978')
        ->and($empty['patient']['document'])->toStartWith('V-');

    TelemedicineHistoryPatient::query()->forceCreate([
        'telemedicine_patient_id' => $this->patient->id,
        'code' => 'HQA-'.random_int(1000, 9999),
        'history_date' => '01/02/2026',
        'created_by' => 'QA',
        'tension_alta_app' => 1, 'input_tension_alta_app' => 'Desde 2019',
        'diabetes' => 1,
        'allergies' => ['Alergia a penicilina y antibióticos betalactámicos'],
        'tabaco' => 1,
    ]);
    $consultation = TelemedicineConsultationPatient::query()->forceCreate([
        'telemedicine_patient_id' => $this->patient->id,
        'telemedicine_case_id' => 0, 'telemedicine_case_code' => 'QA-1', 'telemedicine_doctor_id' => 0,
        'status' => 'CONSULTA INICIAL',
        'reason_consultation' => 'Dolor de cabeza',
        'diagnostic_impression' => '<p>1. Hipertensión arterial descompensada</p>',
        'pa' => 142.88, 'fc' => 78, 'saturacion' => 98, 'peso' => 74,
    ]);
    TelemedicineConsultationPatient::query()->forceCreate(['telemedicine_patient_id' => $this->patient->id, 'telemedicine_case_id' => 0, 'telemedicine_case_code' => 'QA-1', 'telemedicine_doctor_id' => 0, 'status' => 'EN SEGUIMIENTO', 'diagnostic_impression' => 'XXX']);
    TelemedicinePatientMedications::query()->forceCreate([
        'telemedicine_patient_id' => $this->patient->id,
        'telemedicine_consultation_patient_id' => $consultation->id,
        'telemedicine_case_id' => 0,
        'medicine' => 'LOSARTAN 50MG',
        'indications' => 'TOMAR 1 TAB DIARIA POR 10 DIAS',
        'status' => 'PENDIENTE',
    ]);
    TelemedicinePatientLab::query()->forceCreate([
        'telemedicine_patient_id' => $this->patient->id,
        'telemedicine_consultation_patient_id' => $consultation->id,
        'laboratory' => 'PERFIL LIPIDICO',
        'type' => 'LABORATORIO',
        'status' => 'PENDIENTE',
    ]);

    $record = PatientClinicalRecord::build($this->patient->fresh());

    expect(array_column($record['problems'], 'text'))->toBe(['1. Hipertensión arterial descompensada'])
        ->and($record['alerts']['allergies'])->toBe(['Alergia a penicilina y antibióticos betalactámicos'])
        ->and($record['alerts']['conditions'])->toBe(['Hipertensión arterial (Desde 2019)'])
        ->and($record['background']['family'])->toBe(['Diabetes mellitus'])
        ->and($record['background']['habits'])->toBe(['Tabaquismo'])
        ->and($record['medications'][0]['state'])->toBe('En curso')
        ->and($record['alerts']['medications'])->toBe(['LOSARTAN 50MG'])
        ->and($record['vitals'][0]['pa'])->toBe('142/88')
        ->and($record['pending'][0]['item'])->toBe('Laboratorio: PERFIL LIPIDICO')
        ->and($record['last_consultation']['a'])->toBe('1. Hipertensión arterial descompensada')
        ->and($record['last_consultation']['p'])->toContain('LOSARTAN 50MG')
        ->and(collect($record['timeline'])->pluck('source')->all())->toContain('consultation');
});

it('lee fechas en varios formatos y la duración escrita en las indicaciones', function (): void {
    expect(PatientClinicalRecord::parseDate('17/02/2026')?->format('Y-m-d'))->toBe('2026-02-17')
        ->and(PatientClinicalRecord::parseDate('6/8/1978')?->format('Y-m-d'))->toBe('1978-08-06')
        ->and(PatientClinicalRecord::parseDate('2025-02-02')?->format('Y-m-d'))->toBe('2025-02-02')
        ->and(PatientClinicalRecord::parseDate('31/02/2026'))->toBeNull()
        ->and(PatientClinicalRecord::parseDate('basura'))->toBeNull()
        ->and(PatientClinicalRecord::daysFromText('1 CAP CADA 12 HRS POR 10 DIAS'))->toBe(10)
        ->and(PatientClinicalRecord::daysFromText('por 2 semanas'))->toBe(14)
        ->and(PatientClinicalRecord::daysFromText('POR 1 MES'))->toBe(30)
        ->and(PatientClinicalRecord::daysFromText('cada 8 horas'))->toBe(0);
});

it('en Operaciones solo la ven analistas TDG y superadmin', function (): void {
    $ops = ClinicalRecordAccess::PANEL_OPERATIONS;

    expect(ClinicalRecordAccess::canView(($this->user)(['OPERACIONES']), $this->patient, $ops))->toBeTrue()
        ->and(ClinicalRecordAccess::canView(($this->user)(['SUPERADMIN']), $this->patient, $ops))->toBeTrue()
        ->and(ClinicalRecordAccess::canView(($this->user)(['OPERACIONES'], ['supplier_id' => 5]), $this->patient, $ops))->toBeFalse()
        ->and(ClinicalRecordAccess::canView(($this->user)(['OPERACIONES', 'ATENMEDI']), $this->patient, $ops))->toBeFalse()
        ->and(ClinicalRecordAccess::canView(($this->user)(['OPERACIONES'], ['email' => 'externo@gmail.com']), $this->patient, $ops))->toBeFalse()
        ->and(ClinicalRecordAccess::canView(($this->user)(['OPERACIONES'], ['status' => 'INACTIVO']), $this->patient, $ops))->toBeFalse()
        ->and(ClinicalRecordAccess::canView(($this->user)(['NEGOCIOS']), $this->patient, $ops))->toBeFalse()
        ->and(ClinicalRecordAccess::canView(null, $this->patient, $ops))->toBeFalse();
});

it('en Telemedicina exige un médico de un equipo que atiende o atendió al paciente', function (): void {
    $tele = ClinicalRecordAccess::PANEL_TELEMEDICINE;

    expect(ClinicalRecordAccess::canView(($this->user)(['TELEMEDICINA']), $this->patient, $tele))->toBeFalse()
        ->and(ClinicalRecordAccess::canUsePanel(($this->user)(['TELEMEDICINA']), $tele))->toBeFalse()
        ->and(ClinicalRecordAccess::canUsePanel(($this->user)(['TELEMEDICINA'], ['doctor_id' => 1]), $tele))->toBeTrue();
});

it('registra cada lectura y la muestra al pie de la ficha', function (): void {
    $user = ($this->user)(['OPERACIONES']);

    ClinicalRecordAccess::record($user, $this->patient, ClinicalRecordAccess::PANEL_OPERATIONS, ClinicalRecordAccessLog::ACTION_VIEW, '10.0.0.1');
    ClinicalRecordAccess::record($user, $this->patient, ClinicalRecordAccess::PANEL_OPERATIONS, ClinicalRecordAccessLog::ACTION_PDF);

    $recent = ClinicalRecordAccess::recent($this->patient);

    expect(ClinicalRecordAccessLog::query()->where('telemedicine_patient_id', $this->patient->id)->count())->toBe(2)
        ->and($recent[0]['action'])->toBe('Descargó el PDF de la ficha clínica')
        ->and($recent[1]['action'])->toBe('Abrió la ficha clínica')
        ->and($recent[0]['panel'])->toBe('Operaciones');
});

it('el PDF se guarda en el disco privado y solo lo descarga quien lo pidió', function (): void {
    Storage::fake('local');
    $user = ($this->user)(['OPERACIONES']);

    $file = ClinicalRecordPdf::store($this->patient, (int) $user->id, 'Ana Pérez');

    expect(Storage::disk('local')->exists(ClinicalRecordPdf::DIRECTORY.'/'.$file))->toBeTrue()
        ->and(substr(Storage::disk('local')->get(ClinicalRecordPdf::DIRECTORY.'/'.$file), 0, 4))->toBe('%PDF')
        ->and(ClinicalRecordPdf::owner($file))->toBe(['user_id' => (int) $user->id, 'patient_id' => (int) $this->patient->id, 'kind' => 'clinica'])
        ->and(ClinicalRecordPdf::owner('../../.env'))->toBeNull()
        ->and(ClinicalRecordPdf::owner('1-2-x.pdf'))->toBeNull();

    $other = ($this->user)(['OPERACIONES']);
    $request = Request::create('/ficha-clinica/pdf/'.$file);
    $request->setUserResolver(fn () => $other);

    try {
        (new ClinicalRecordPdfController)($request, $file);
        $status = 200;
    } catch (Symfony\Component\HttpKernel\Exception\HttpException $exception) {
        $status = $exception->getStatusCode();
    }

    $request->setUserResolver(fn () => $user);
    $response = (new ClinicalRecordPdfController)($request, $file);

    expect($status)->toBe(403)
        ->and($response->headers->get('content-disposition'))->toContain('Ficha clinica - Maria Jose Qa Rivas.pdf');
});

it('el job no genera el PDF si el usuario ya no tiene acceso', function (): void {
    Storage::fake('local');
    $userId = (int) DB::table('users')->where('status', 'ACTIVO')->whereJsonContains('departament', 'NEGOCIOS')->whereJsonDoesntContain('departament', 'SUPERADMIN')->whereJsonDoesntContain('departament', 'OPERACIONES')->value('id');

    if ($userId === 0) {
        return;
    }

    (new GenerateClinicalRecordPdfJob((int) $this->patient->id, $userId, ClinicalRecordAccess::PANEL_OPERATIONS))->handle();

    expect(Storage::disk('local')->files(ClinicalRecordPdf::DIRECTORY))->toBe([])
        ->and(ClinicalRecordAccessLog::query()->where('telemedicine_patient_id', $this->patient->id)->count())->toBe(0);
});

it('la ficha está en la tabla y en la vista del paciente de ambos paneles', function (): void {
    foreach (['Telemedicina', 'Operations'] as $panel) {
        expect(file_get_contents(app_path("Filament/{$panel}/Resources/TelemedicinePatients/TelemedicinePatientResource.php")))->toContain("'clinical-record' => PatientClinicalRecord::route('/{record}/ficha-clinica')")
            ->and(file_get_contents(app_path("Filament/{$panel}/Resources/TelemedicinePatients/Tables/TelemedicinePatientsTable.php")))->toContain('ClinicalRecordAction::make(')
            ->and(file_get_contents(app_path("Filament/{$panel}/Resources/TelemedicinePatients/Pages/ViewTelemedicinePatient.php")))->toContain('ClinicalRecordAction::make(');
    }
});

it('la ficha técnica solo existe en Operaciones y queda en la bitácora como técnica', function (): void {
    $analyst = ($this->user)(['OPERACIONES']);
    $doctor = ($this->user)(['TELEMEDICINA'], ['doctor_id' => 1]);
    $tech = ClinicalRecordAccess::KIND_TECHNICAL;

    expect(ClinicalRecordAccess::canView($analyst, $this->patient, ClinicalRecordAccess::PANEL_OPERATIONS, $tech))->toBeTrue()
        ->and(ClinicalRecordAccess::canView($doctor, $this->patient, ClinicalRecordAccess::PANEL_TELEMEDICINE, $tech))->toBeFalse()
        ->and(ClinicalRecordAccess::canUsePanel($doctor, ClinicalRecordAccess::PANEL_TELEMEDICINE, $tech))->toBeFalse()
        ->and(ClinicalRecordAccess::canView(($this->user)(['OPERACIONES'], ['supplier_id' => 3]), $this->patient, ClinicalRecordAccess::PANEL_OPERATIONS, $tech))->toBeFalse();

    ClinicalRecordAccess::record($analyst, $this->patient, ClinicalRecordAccess::PANEL_OPERATIONS, ClinicalRecordAccessLog::ACTION_VIEW, null, $tech);
    ClinicalRecordAccess::record($analyst, $this->patient, ClinicalRecordAccess::PANEL_OPERATIONS, ClinicalRecordAccessLog::ACTION_PDF, null, $tech);

    expect(array_column(ClinicalRecordAccess::recent($this->patient), 'action'))->toBe(['Descargó el PDF de la ficha técnica', 'Abrió la ficha técnica']);
});

it('el PDF técnico lleva afiliación, cupos y cortesías; el clínico no', function (): void {
    Storage::fake('local');
    $user = ($this->user)(['OPERACIONES']);

    $technical = ClinicalRecordPdf::store($this->patient, (int) $user->id, 'Ana Pérez', ClinicalRecordAccess::KIND_TECHNICAL);
    $html = fn (bool $technical): string => view('documents.clinical-record', ['record' => PatientClinicalRecord::build($this->patient), 'issuer' => 'QA', 'technical' => $technical, 'logo' => ''])->render();

    expect(ClinicalRecordPdf::owner($technical)['kind'])->toBe('tecnica')
        ->and($html(true))->toContain('USO INTERNO')->toContain('Identificación, afiliación y cobertura')->toContain('Servicios de cortesía')
        ->and($html(false))->toContain('Datos del paciente')->not->toContain('Servicios de cortesía')->not->toContain('USO INTERNO')->not->toContain('Vigencia');
});

it('lista los servicios de cortesía del paciente', function (): void {
    TelemedicinePatientMedications::query()->forceCreate([
        'telemedicine_patient_id' => $this->patient->id, 'telemedicine_case_id' => 0,
        'medicine' => 'BROMEXINA JARABE', 'indications' => '10 CC CADA 8 HORAS',
        'courtesy_status' => 'CORTESIA', 'courtesy_reason' => 'Aprobado por coordinación', 'courtesy_updated_by' => 'Ana Pérez', 'courtesy_updated_at' => '2026-11-21 10:00:00',
    ]);
    TelemedicinePatientMedications::query()->forceCreate([
        'telemedicine_patient_id' => $this->patient->id, 'telemedicine_case_id' => 0,
        'medicine' => 'LORATADINA', 'indications' => '1 DIARIA',
    ]);

    $courtesies = PatientClinicalRecord::build($this->patient)['courtesies'];

    expect($courtesies)->toHaveCount(1)
        ->and($courtesies[0]['service'])->toBe('BROMEXINA JARABE')
        ->and($courtesies[0]['date'])->toBe('21/11/2026')
        ->and($courtesies[0]['reason'])->toBe('Aprobado por coordinación')
        ->and($courtesies[0]['by'])->toBe('Ana Pérez');
});

it('la ficha técnica está en la tabla y la vista del paciente de Operaciones, no en Telemedicina', function (): void {
    expect(file_get_contents(app_path('Filament/Operations/Resources/TelemedicinePatients/TelemedicinePatientResource.php')))->toContain("'technical-record' => PatientTechnicalRecord::route('/{record}/ficha-tecnica')")
        ->and(file_get_contents(app_path('Filament/Operations/Resources/TelemedicinePatients/Tables/TelemedicinePatientsTable.php')))->toContain('ClinicalRecordAccess::KIND_TECHNICAL')
        ->and(file_get_contents(app_path('Filament/Operations/Resources/TelemedicinePatients/Pages/ViewTelemedicinePatient.php')))->toContain('ClinicalRecordAccess::KIND_TECHNICAL')
        ->and(file_get_contents(app_path('Filament/Telemedicina/Resources/TelemedicinePatients/TelemedicinePatientResource.php')))->not->toContain('technical-record');
});
