<?php

declare(strict_types=1);

use App\Models\TelemedicineAmdPhysicalExam;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDoctor;
use App\Models\User;
use App\Support\Operations\TelemedicineCaseBitacora;
use App\Support\Telemedicine\TelemedicineAmdPhysicalExamRegistrar;
use App\Support\Telemedicine\TelemedicineAmdPhysicalExamTemplate;
use App\Support\Telemedicine\TelemedicineInformeLargoDataBuilder;
use App\Support\Telemedicine\TelemedicinePatientCareHistory;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    session()->forget(TelemedicineAmdPhysicalExamRegistrar::SESSION_PENDING_EXAM_ID);
    // created_by / updated_by tienen FK a users: se actúa como un usuario real (sólo lectura).
    $this->usuario = User::query()->orderBy('id')->firstOrFail();
    $this->actingAs($this->usuario);
});

afterEach(fn () => DB::rollBack());

/**
 * Consulta real sin informe AMD: guardar el examen no regenera ningún PDF en disco.
 */
function consultaSinInformeAmd(): ?TelemedicineConsultationPatient
{
    return TelemedicineConsultationPatient::query()
        ->whereNotNull('telemedicine_case_id')
        ->whereNotNull('telemedicine_patient_id')
        ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
            ->from('telemedicine_amd_informs')
            ->whereColumn('telemedicine_amd_informs.telemedicine_consultation_patient_id', 'telemedicine_consultation_patients.id'))
        ->whereHas('telemedicineCase')
        ->latest('id')
        ->first();
}

/**
 * @return array{telemedicine_patient_id: int, telemedicine_case_id: int, telemedicine_doctor_id: int|null}
 */
function contextoDe(TelemedicineConsultationPatient $consulta): array
{
    return [
        'telemedicine_patient_id' => (int) $consulta->telemedicine_patient_id,
        'telemedicine_case_id' => (int) $consulta->telemedicine_case_id,
        'telemedicine_doctor_id' => $consulta->telemedicine_doctor_id,
    ];
}

/*
 * ---------------------------------------------------------------------------
 * Plantilla
 * ---------------------------------------------------------------------------
 */

it('trae los doce sistemas con el texto normal y los signos vitales vacíos', function (): void {
    $defaults = TelemedicineAmdPhysicalExamTemplate::defaults();

    expect(TelemedicineAmdPhysicalExamTemplate::SYSTEMS)->toHaveCount(12)
        ->and($defaults['skin'])->toBe('Normocoloreada, normotérmica, hidratada, elasticidad, grosor y movilidad normales, sin lesiones, llenado capilar <3 seg.; PIEL, CICATRICES, TATUAJES: N/A.')
        ->and($defaults['mental_status'])->toContain('DORSO Y C. VERT: Normal; HERNIAS: No palpables.')
        ->and(array_map(fn (string $column): ?string => $defaults[$column], array_keys(TelemedicineAmdPhysicalExamTemplate::VITALS)))
        ->each->toBeNull()
        ->and(array_column(TelemedicineAmdPhysicalExamTemplate::SYSTEMS, 0))->toBe([
            'PIEL', 'CABEZA, CRÁNEO', 'OÍDOS', 'NARIZ', 'BOCA', 'CUELLO Y TIROIDES', 'TÓRAX',
            'ABDOMEN', 'GENITOURINARIO', 'EXTREMIDADES', 'SISTEMA NERVIOSO', 'ESTADO MENTAL',
        ]);
});

it('sólo considera editado un sistema cuyo texto cambió', function (?string $valor, bool $editado): void {
    expect(TelemedicineAmdPhysicalExamTemplate::isEdited('neck', $valor))->toBe($editado);
})->with([
    'texto normal' => ['Simétrico. Ausencia de tumoraciones, sin adenopatías. Tiroides Grado 0.', false],
    'normal con espacios de más' => ["  Simétrico.  Ausencia de tumoraciones,\nsin adenopatías. Tiroides Grado 0. ", false],
    'hallazgo distinto' => ['Bocio grado II.', true],
    'vacío' => ['', true],
    'nulo' => [null, true],
]);

it('el enlace se llama «Restaurar texto por defecto» y sólo aparece al editar', function (): void {
    $concern = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Concerns/HasAmdPhysicalExamModal.php');

    expect($concern)->toContain("->label('Restaurar texto por defecto')")
        ->toContain('TelemedicineAmdPhysicalExamTemplate::isEdited($column, $get($column))')
        ->toContain('->live(debounce: 600)')
        ->not->toContain('Restaurar texto normal');
});

it('muestra signos con su unidad sin repetirla y marca lo que el médico cambió', function (): void {
    $examen = new TelemedicineAmdPhysicalExam([
        ...TelemedicineAmdPhysicalExamTemplate::defaults(),
        'heart_rate' => '80',
        'oxygen_saturation' => '98 %',
        'blood_pressure' => '110/70mmHg',
        'abdomen' => 'Doloroso a la palpación en fosa ilíaca derecha.',
        'ears' => '',
    ]);

    $vista = TelemedicineAmdPhysicalExamTemplate::display($examen);
    $abdomen = collect($vista['systems'])->firstWhere('label', 'ABDOMEN');

    expect($vista['vitals'])->toBe([
        ['label' => 'Frecuencia cardíaca', 'value' => '80 lpm'],
        ['label' => 'Presión arterial', 'value' => '110/70mmHg'],
        ['label' => 'SpO2', 'value' => '98 %'],
    ])->and($abdomen['is_default'])->toBeFalse()
        ->and(collect($vista['systems'])->firstWhere('label', 'PIEL')['is_default'])->toBeTrue()
        ->and(collect($vista['systems'])->pluck('label'))->not->toContain('OÍDOS');
});

/*
 * ---------------------------------------------------------------------------
 * Registro (transacción revertida)
 * ---------------------------------------------------------------------------
 */

it('guarda el examen pendiente y al reabrirlo edita el mismo', function (): void {
    $consulta = consultaSinInformeAmd() ?? $this->markTestSkipped('No hay consultas.');

    $primero = TelemedicineAmdPhysicalExamRegistrar::save(contextoDe($consulta), [...TelemedicineAmdPhysicalExamTemplate::defaults(), 'heart_rate' => '80']);
    $segundo = TelemedicineAmdPhysicalExamRegistrar::save(
        contextoDe($consulta),
        [...TelemedicineAmdPhysicalExamTemplate::defaults(), 'heart_rate' => '92', 'skin' => '  '],
        pendingExamId: (int) $primero->id,
    );

    expect($segundo->id)->toBe($primero->id)
        ->and($segundo->heart_rate)->toBe('92')
        ->and($segundo->skin)->toBeNull()
        ->and($segundo->telemedicine_consultation_patient_id)->toBeNull()
        ->and($segundo->created_by)->toBe($this->usuario->id)
        ->and(TelemedicineAmdPhysicalExam::query()->where('telemedicine_case_id', $consulta->telemedicine_case_id)->count())->toBe(1);
});

it('al guardar la consulta vincula el examen pendiente y queda uno solo', function (): void {
    $consulta = consultaSinInformeAmd() ?? $this->markTestSkipped('No hay consultas.');

    $pendiente = TelemedicineAmdPhysicalExamRegistrar::save(contextoDe($consulta), TelemedicineAmdPhysicalExamTemplate::defaults());
    $vinculado = TelemedicineAmdPhysicalExamRegistrar::attachPendingToConsultation($consulta, (int) $pendiente->id);

    $editado = TelemedicineAmdPhysicalExamRegistrar::save(
        contextoDe($consulta),
        [...TelemedicineAmdPhysicalExamTemplate::defaults(), 'pulse' => '70'],
        consultation: $consulta,
    );

    expect($vinculado->id)->toBe($pendiente->id)
        ->and($vinculado->telemedicine_consultation_patient_id)->toBe($consulta->id)
        ->and($editado->id)->toBe($pendiente->id)
        ->and($editado->pulse)->toBe('70')
        ->and(TelemedicineAmdPhysicalExam::query()->where('telemedicine_consultation_patient_id', $consulta->id)->count())->toBe(1)
        ->and(session()->has(TelemedicineAmdPhysicalExamRegistrar::SESSION_PENDING_EXAM_ID))->toBeFalse();
});

it('sin examen pendiente no vincula nada', function (): void {
    $consulta = consultaSinInformeAmd() ?? $this->markTestSkipped('No hay consultas.');

    expect(TelemedicineAmdPhysicalExamRegistrar::attachPendingToConsultation($consulta))->toBeNull();
});

it('no guarda un examen sin caso o sin paciente', function (): void {
    TelemedicineAmdPhysicalExamRegistrar::save(
        ['telemedicine_patient_id' => 0, 'telemedicine_case_id' => 0],
        TelemedicineAmdPhysicalExamTemplate::defaults(),
    );
})->throws(InvalidArgumentException::class);

it('recorta un signo vital que excede el largo de la columna', function (): void {
    $consulta = consultaSinInformeAmd() ?? $this->markTestSkipped('No hay consultas.');

    $examen = TelemedicineAmdPhysicalExamRegistrar::save(contextoDe($consulta), ['heart_rate' => str_repeat('9', 80)]);

    expect(mb_strlen((string) $examen->heart_rate))->toBe(TelemedicineAmdPhysicalExamTemplate::VITAL_MAX_LENGTH);
});

/*
 * ---------------------------------------------------------------------------
 * Dónde se ve
 * ---------------------------------------------------------------------------
 */

it('llega al informe médico largo, a la bitácora y a las consultas anteriores', function (): void {
    $consulta = consultaSinInformeAmd() ?? $this->markTestSkipped('No hay consultas.');

    TelemedicineAmdPhysicalExamRegistrar::save(
        contextoDe($consulta),
        [...TelemedicineAmdPhysicalExamTemplate::defaults(), 'heart_rate' => '84'],
        consultation: $consulta,
    );

    $doctor = TelemedicineDoctor::query()->find($consulta->telemedicine_doctor_id) ?? new TelemedicineDoctor(['full_name' => 'DR. X']);
    $informe = TelemedicineInformeLargoDataBuilder::buildFromContext([
        ...$consulta->toArray(),
        'telemedicine_consultation_id' => $consulta->id,
    ], $doctor);

    $bitacora = TelemedicineCaseBitacora::amdPhysicalExamRows((int) $consulta->telemedicine_case_id);
    $consultas = collect(TelemedicinePatientCareHistory::consultationsByCase($consulta->telemedicinePatient))
        ->flatMap(fn (array $caso): array => $caso['consultations'])
        ->first(fn (array $c): bool => $c['reference'] === ($consulta->code_reference ?: 'CONS-'.$consulta->id));

    expect($informe['physical_exam']['vitals'][0])->toBe(['label' => 'Frecuencia cardíaca', 'value' => '84 lpm'])
        ->and($informe['physical_exam']['systems'])->toHaveCount(12)
        ->and($bitacora)->toHaveCount(1)
        ->and($bitacora[0]['systems'][0]['label'])->toBe('PIEL')
        ->and($consultas['physical_exam']['systems'])->toHaveCount(12);
});

it('un informe de una consulta sin examen no trae la sección', function (): void {
    $consulta = consultaSinInformeAmd() ?? $this->markTestSkipped('No hay consultas.');

    $informe = TelemedicineInformeLargoDataBuilder::buildFromContext([
        ...$consulta->toArray(),
        'telemedicine_consultation_id' => $consulta->id,
    ], new TelemedicineDoctor(['full_name' => 'DR. X']));

    expect($informe['physical_exam'])->toBeNull();
});

it('las vistas del informe y de la bitácora pintan sistema y hallazgo', function (): void {
    $examen = TelemedicineAmdPhysicalExamTemplate::display(new TelemedicineAmdPhysicalExam([
        ...TelemedicineAmdPhysicalExamTemplate::defaults(),
        'heart_rate' => '80',
        'thorax' => 'Murmullo vesicular disminuido en base derecha.',
    ]));
    $entrada = ['date' => '28/09/2026 10:00', 'doctor' => 'DRA. PRUEBA', 'reference' => 'REF-1', ...$examen];

    $informe = view('documents.partials.informe-examen-fisico-amd', ['exam' => $examen])->render();
    $pdf = view('documents.partials.bitacora-caso-amd-physical-exams', ['entries' => [$entrada]])->render();
    $pantalla = view('filament.operations.partials.bitacora-caso-amd-physical-exams', ['entries' => [$entrada]])->render();
    $sinExamen = view('documents.partials.informe-examen-fisico-amd', ['exam' => null])->render();

    expect($informe)->toContain('Examen físico')->toContain('Frecuencia cardíaca')->toContain('80 lpm')->toContain('TÓRAX:')
        ->and($pdf)->toContain('Examen físico AMD')->toContain('PIEL:')->toContain('DRA. PRUEBA')
        ->and($pantalla)->toContain('Murmullo vesicular disminuido')->toContain('hallazgos que el médico modificó')
        ->and(trim($sinExamen))->toBe('');
});

it('el botón existe en crear y editar consulta y el examen se vincula antes que el informe', function (): void {
    $base = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/';
    $crear = file_get_contents($base.'Pages/CreateTelemedicineConsultationPatient.php');
    $editar = file_get_contents($base.'Pages/EditTelemedicineConsultationPatient.php');
    $disparador = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/telemedicina/consultations/inform-amd-trigger.blade.php');

    expect($crear)->toContain('use HasAmdPhysicalExamModal;')
        ->and($editar)->toContain('use HasAmdPhysicalExamModal;')
        ->and($disparador)->toContain("mountAction('amdPhysicalExam')")
        ->and(strpos($crear, 'TelemedicineAmdPhysicalExamRegistrar::attachPendingToConsultation('))
        ->toBeLessThan(strpos($crear, 'TelemedicineAmdInformRegistrar::attachPendingToConsultation('));
});
