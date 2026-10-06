<?php

declare(strict_types=1);

use App\Jobs\GeneratePdfInformeMedicoLargo;
use App\Jobs\GeneratePdfMedicamentos;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicinePatientMedications;
use App\Models\User;
use App\Support\Telemedicine\TelemedicineCaseDocumentRegenerationResult;
use App\Support\Telemedicine\TelemedicineCaseDocumentRegenerationService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

uses(Tests\TestCase::class);

/**
 * Consulta en memoria con su médico ya cargado, como la deja consultationsOf().
 *
 * @param  array<string, mixed>  $attributes
 */
function telemedicineRegenerationConsultation(int $id, array $attributes, ?TelemedicineDoctor $doctor): TelemedicineConsultationPatient
{
    $consultation = new TelemedicineConsultationPatient(array_merge([
        'telemedicine_service_list_id' => 1,
        'telemedicine_doctor_id' => $doctor?->id,
    ], $attributes));
    $consultation->id = $id;
    $consultation->telemedicine_case_id = 20;
    $consultation->setRelation('telemedicineDoctor', $doctor);

    return $consultation;
}

function telemedicineRegenerationDoctor(int $id, string $name, string $signature): TelemedicineDoctor
{
    $doctor = new TelemedicineDoctor([
        'full_name' => $name,
        'code_mpps' => 'MPPS-'.$id,
        'signature' => $signature,
    ]);
    $doctor->id = $id;

    return $doctor;
}

/**
 * El payload del job es protegido: se lee tal como lo recibirá la plantilla.
 *
 * @return array<string, mixed>
 */
function telemedicineRegenerationJobPayload(object $job): array
{
    return (fn (): array => $this->data)->call($job);
}

/**
 * Servicio real con solo las fronteras de I/O sustituidas: las consultas del
 * caso, el paciente, los ítems por consulta y la ejecución del job. Todo lo
 * demás —selección, firmante, armado de payloads y control de fallos— es el
 * código de producción.
 *
 * Caso del pool: el médico A abre el caso y receta; el médico B toma el
 * seguimiento y receta otra cosa.
 *
 * @param  list<TelemedicineConsultationPatient>|null  $consultations
 * @param  array<int, list<string>>|null  $medicationsByConsultation
 */
function telemedicineRegenerationServiceForTest(
    ?callable $onRun = null,
    ?array $consultations = null,
    ?array $medicationsByConsultation = null,
): TelemedicineCaseDocumentRegenerationService {
    $doctorA = telemedicineRegenerationDoctor(5, 'CAROLINA PINILLO', 'firmas-medicos/a.png');
    $doctorB = telemedicineRegenerationDoctor(16, 'ANGEL VALERIO', 'firmas-medicos/b.png');

    $consultations ??= [
        telemedicineRegenerationConsultation(22, [
            'status' => 'CONSULTA INICIAL',
            'reason_consultation' => 'FIEBRE',
            'code_reference' => 'REF-22',
            'observations' => 'Paciente ansioso, control en 24 horas.',
        ], $doctorA),
        telemedicineRegenerationConsultation(30, [
            'status' => 'EN SEGUIMIENTO',
            'current_illness_history' => 'PERSISTE FIEBRE',
            'patient_evolution' => 'ESTABLE',
            'diagnostic_impression' => 'SINDROME FEBRIL',
            'code_reference' => 'REF-30',
            'observations' => '',
        ], $doctorB),
    ];

    $medicationsByConsultation ??= [
        22 => ['PARACETAMOL'],
        30 => ['IBUPROFENO'],
    ];

    return new class($onRun, $consultations, $medicationsByConsultation) extends TelemedicineCaseDocumentRegenerationService
    {
        /** @var list<object> */
        public array $executed = [];

        /**
         * @param  list<TelemedicineConsultationPatient>  $consultations
         * @param  array<int, list<string>>  $medicationsByConsultation
         */
        public function __construct(
            private $onRun,
            private array $consultations,
            private array $medicationsByConsultation,
        ) {}

        public function consultationsOf(TelemedicineCase $case): \Illuminate\Support\Collection
        {
            return collect($this->consultations);
        }

        protected function resolvePatient(TelemedicineConsultationPatient $consultation, TelemedicineCase $case): ?TelemedicinePatient
        {
            $patient = new TelemedicinePatient(['nro_identificacion' => '123']);
            $patient->id = 7;

            return $patient;
        }

        protected function medicationsForConsultation(TelemedicineConsultationPatient $consultation): \Illuminate\Support\Collection
        {
            return collect($this->medicationsByConsultation[(int) $consultation->id] ?? [])
                ->map(static fn (string $medicine): TelemedicinePatientMedications => new TelemedicinePatientMedications([
                    'medicine' => $medicine,
                    'indications' => '1 CADA 8H',
                    'duration' => '3 DIAS',
                ]));
        }

        protected function labsSplitForConsultation(TelemedicineConsultationPatient $consultation): array
        {
            return [[], []];
        }

        protected function studiesSplitForConsultation(TelemedicineConsultationPatient $consultation): array
        {
            return [[], []];
        }

        protected function specialistsSplitForConsultation(TelemedicineConsultationPatient $consultation): array
        {
            return [[], []];
        }

        protected function runJob(object $job): void
        {
            $this->executed[] = $job;

            if ($this->onRun !== null) {
                ($this->onRun)($job);
            }
        }
    };
}

it('ofrece por consulta solo los documentos que esa consulta registró', function (): void {
    $service = telemedicineRegenerationServiceForTest(medicationsByConsultation: [22 => ['PARACETAMOL']]);
    $case = telemedicineRegenerationTestCase();

    $initial = $service->consultationOf($case, 22);
    $followUp = $service->consultationOf($case, 30);

    expect($service->availableOptions($initial))
        ->toHaveKey(TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_MEDICO)
        ->toHaveKey(TelemedicineCaseDocumentRegenerationService::DOCUMENT_MEDICAMENTOS)
        ->not->toHaveKey(TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_SEGUIMIENTO)
        ->not->toHaveKey(TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_CORTO);

    // El seguimiento no recetó: no hereda el récipe de la consulta inicial.
    expect($service->availableOptions($followUp))
        ->toHaveKey(TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_SEGUIMIENTO)
        ->not->toHaveKey(TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_MEDICO)
        ->not->toHaveKey(TelemedicineCaseDocumentRegenerationService::DOCUMENT_MEDICAMENTOS);
});

it('cada consulta se firma con su propio médico y solo con lo que ese médico indicó', function (): void {
    Bus::fake();
    Queue::fake();

    $service = telemedicineRegenerationServiceForTest();
    $case = telemedicineRegenerationTestCase();
    $user = telemedicineRegenerationTestUser();

    $service->regenerate($case, 22, [TelemedicineCaseDocumentRegenerationService::DOCUMENT_MEDICAMENTOS], $user);
    $service->regenerate($case, 30, [
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_MEDICAMENTOS,
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_SEGUIMIENTO,
    ], $user);

    [$recipeA, $recipeB, $followUpReport] = array_map(telemedicineRegenerationJobPayload(...), $service->executed);

    expect($recipeA['doctor_name'])->toBe('CAROLINA PINILLO')
        ->and($recipeA['signature'])->toBe('firmas-medicos/a.png')
        ->and($recipeA['code_reference'])->toBe('REF-22')
        ->and(array_column($recipeA['medicationsArr'], 'medicines'))->toBe(['PARACETAMOL']);

    // Lo que recetó B lleva la firma de B, con la referencia del seguimiento:
    // no pisa el récipe de la consulta inicial.
    expect($recipeB['doctor_name'])->toBe('ANGEL VALERIO')
        ->and($recipeB['signature'])->toBe('firmas-medicos/b.png')
        ->and($recipeB['code_reference'])->toBe('REF-30')
        ->and(array_column($recipeB['medicationsArr'], 'medicines'))->toBe(['IBUPROFENO']);

    expect($followUpReport['doctor_name'])->toBe('ANGEL VALERIO')
        ->and($followUpReport['signature'])->toBe('firmas-medicos/b.png')
        ->and($followUpReport['telemedicine_consultation_id'])->toBe(30);
});

it('una consulta sin médico no se regenera ni se firma con el médico del caso', function (): void {
    $doctorA = telemedicineRegenerationDoctor(5, 'CAROLINA PINILLO', 'firmas-medicos/a.png');
    $service = telemedicineRegenerationServiceForTest(consultations: [
        telemedicineRegenerationConsultation(22, [
            'status' => 'CONSULTA INICIAL',
            'reason_consultation' => 'FIEBRE',
            'code_reference' => 'REF-22',
        ], $doctorA),
        telemedicineRegenerationConsultation(30, [
            'status' => 'EN SEGUIMIENTO',
            'code_reference' => 'REF-30',
        ], null),
    ]);
    $case = telemedicineRegenerationTestCase();
    $case->telemedicine_doctor_id = 5;

    expect($service->consultationIdsWithoutDoctor($case))->toBe([30])
        ->and($service->defaultConsultationId($case))->toBe(22)
        ->and($service->consultationLabel($service->consultationOf($case, 30)))->toContain('sin médico registrado');

    expect(fn () => $service->regenerate($case, 30, [
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_MEDICAMENTOS,
    ], telemedicineRegenerationTestUser()))
        ->toThrow(InvalidArgumentException::class, TelemedicineCaseDocumentRegenerationService::MISSING_DOCTOR_MESSAGE);

    expect($service->executed)->toBe([]);
});

it('rechaza una consulta que no pertenece al caso', function (): void {
    $service = telemedicineRegenerationServiceForTest();

    expect(fn () => $service->regenerate(telemedicineRegenerationTestCase(), 999, [
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_MEDICAMENTOS,
    ], telemedicineRegenerationTestUser()))
        ->toThrow(InvalidArgumentException::class, 'Seleccione una consulta de este caso');

    expect(fn () => $service->regenerate(telemedicineRegenerationTestCase(), null, [
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_MEDICAMENTOS,
    ], telemedicineRegenerationTestUser()))
        ->toThrow(InvalidArgumentException::class);
});

it('el informe regenerado lleva la observación de su consulta y nada si está vacía', function (): void {
    Bus::fake();
    Queue::fake();

    $service = telemedicineRegenerationServiceForTest();
    $case = telemedicineRegenerationTestCase();
    $user = telemedicineRegenerationTestUser();

    $service->regenerate($case, 22, [TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_MEDICO], $user);
    $service->regenerate($case, 30, [TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_SEGUIMIENTO], $user);

    [$informe, $seguimiento] = array_map(telemedicineRegenerationJobPayload(...), $service->executed);

    expect($informe['observations'])->toBe('Paciente ansioso, control en 24 horas.')
        ->and($seguimiento['observations'])->toBe('');
});

function telemedicineRegenerationTestCase(): TelemedicineCase
{
    $case = new TelemedicineCase(['code' => 'TM-2']);
    $case->id = 20;

    return $case;
}

function telemedicineRegenerationTestUser(): User
{
    $user = new User(['name' => 'Dr Test']);
    $user->id = 9;

    return $user;
}

it('genera los documentos en el request sin tocar la cola', function (): void {
    Bus::fake();
    Queue::fake();

    $service = telemedicineRegenerationServiceForTest();

    $result = $service->regenerate(telemedicineRegenerationTestCase(), 22, [
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_MEDICO,
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_MEDICAMENTOS,
    ], telemedicineRegenerationTestUser());

    expect($result->generated)->toBe([
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_MEDICO,
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_MEDICAMENTOS,
    ])
        ->and($result->failed)->toBe([])
        ->and($result->allGenerated())->toBeTrue()
        ->and($service->executed)->toHaveCount(2)
        ->and($service->executed[0])->toBeInstanceOf(GeneratePdfInformeMedicoLargo::class)
        ->and($service->executed[1])->toBeInstanceOf(GeneratePdfMedicamentos::class);

    // El sentido de esta acción es funcionar cuando la cola está caída.
    Bus::assertNothingBatched();
    Queue::assertNothingPushed();
});

it('un documento que falla no impide generar los demás', function (): void {
    Bus::fake();
    Queue::fake();

    $service = telemedicineRegenerationServiceForTest(function (object $job): void {
        if ($job instanceof GeneratePdfMedicamentos) {
            throw new RuntimeException('Disco lleno');
        }
    });

    $result = $service->regenerate(telemedicineRegenerationTestCase(), 22, [
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_MEDICO,
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_MEDICAMENTOS,
    ], telemedicineRegenerationTestUser());

    expect($result->generated)->toBe([TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_MEDICO])
        ->and($result->failed)->toHaveKey(TelemedicineCaseDocumentRegenerationService::DOCUMENT_MEDICAMENTOS)
        ->and($result->failed[TelemedicineCaseDocumentRegenerationService::DOCUMENT_MEDICAMENTOS])->toBe('Disco lleno')
        ->and($result->allGenerated())->toBeFalse()
        ->and($result->noneGenerated())->toBeFalse()
        ->and($result->failedLabels())->not->toBeEmpty();
});

it('informa cuando ningún documento pudo generarse', function (): void {
    Bus::fake();
    Queue::fake();

    $service = telemedicineRegenerationServiceForTest(function (): void {
        throw new RuntimeException('Fallo de plantilla');
    });

    $result = $service->regenerate(telemedicineRegenerationTestCase(), 22, [
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_MEDICO,
    ], telemedicineRegenerationTestUser());

    expect($result->noneGenerated())->toBeTrue()
        ->and($result->generatedCount())->toBe(0)
        ->and($result->failedCount())->toBe(1);
});

it('rechaza una selección vacía o inexistente', function (): void {
    $service = telemedicineRegenerationServiceForTest();

    expect(fn () => $service->regenerate(telemedicineRegenerationTestCase(), 22, [], telemedicineRegenerationTestUser()))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $service->regenerate(telemedicineRegenerationTestCase(), 22, ['documento-inventado'], telemedicineRegenerationTestUser()))
        ->toThrow(InvalidArgumentException::class);
});

it('la accion filament usa checkbox list y el servicio de regeneracion', function (): void {
    $action = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineCases/Actions/RegenerateTelemedicineCaseDocumentsAction.php');
    $dash = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Widgets/TelemedicineCaseTableDash.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineCases/Tables/TelemedicineCasesTable.php');
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineCaseDocumentRegenerationService.php');

    expect($action)
        ->toContain('Generar documentos')
        ->toContain("CheckboxList::make('documents')")
        ->toContain("Select::make('consultation_id')")
        ->toContain('disableOptionWhen')
        ->toContain('MISSING_DOCTOR_MESSAGE')
        ->toContain('TelemedicineCaseDocumentRegenerationService')
        ->toContain('bulkToggleable');

    expect($dash)->toContain('RegenerateTelemedicineCaseDocumentsAction::make');
    expect($table)->toContain('RegenerateTelemedicineCaseDocumentsAction::make');

    expect($service)
        ->toContain('GeneratePdfInformeMedicoLargo')
        ->not->toContain('GeneratePdfInformeMedicoCorto')
        ->toContain('GeneratePdfInformeSeguimiento')
        ->toContain('GeneratePdfMedicamentos')
        ->toContain('GeneratePdfLaboratorio')
        ->toContain('GeneratePdfImagenologia')
        ->toContain('GeneratePdfEspecialista')
        ->toContain('dispatch_sync')
        ->not->toContain("->onQueue('telemedicina')")
        ->toContain('labsSplitForConsultation')
        ->toContain('TelemedicineMedicationCoverage::isCovered');
});

it('el resultado distingue éxito total, parcial y fallo completo', function (): void {
    $labels = [
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_MEDICO => 'Informe médico',
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_MEDICAMENTOS => 'Récipe',
    ];

    $todo = new TelemedicineCaseDocumentRegenerationResult(array_keys($labels), [], $labels);
    $parcial = new TelemedicineCaseDocumentRegenerationResult(
        [TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_MEDICO],
        [TelemedicineCaseDocumentRegenerationService::DOCUMENT_MEDICAMENTOS => 'Disco lleno'],
        $labels,
    );
    $ninguno = new TelemedicineCaseDocumentRegenerationResult(
        [],
        [TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_MEDICO => 'Error'],
        $labels,
    );

    expect($todo->allGenerated())->toBeTrue()
        ->and($todo->noneGenerated())->toBeFalse()
        ->and($parcial->allGenerated())->toBeFalse()
        ->and($parcial->noneGenerated())->toBeFalse()
        ->and($parcial->failedLabels())->toBe(['Récipe'])
        ->and($parcial->generatedLabels())->toBe(['Informe médico'])
        ->and($ninguno->noneGenerated())->toBeTrue()
        ->and($ninguno->failedCount())->toBe(1);
});

it('la acción avisa del resultado real y ya no promete un proceso en cola', function (): void {
    $action = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineCases/Actions/RegenerateTelemedicineCaseDocumentsAction.php');

    expect($action)
        ->toContain('notifyResult')
        ->toContain('Generación parcial')
        ->toContain('No se pudo generar ningún documento')
        ->toContain('sin pasar por la cola')
        // El texto viejo prometía un aviso posterior que, con la cola caída, nunca llegaba.
        ->not->toContain('Recibirá una notificación al finalizar')
        ->not->toContain('Se regenerarán en segundo plano');
});

it('tras generar lleva al médico al expediente documental del caso', function (): void {
    $case = telemedicineRegenerationTestCase();

    $url = App\Filament\Telemedicina\Resources\TelemedicineCases\Actions\RegenerateTelemedicineCaseDocumentsAction::caseDocumentsTabUrl($case);

    expect($url)->toContain('/telemedicina/telemedicine-cases/'.$case->id)
        // La pestaña se selecciona con `<id>::tab`, no con el slug pelado.
        ->toContain(rawurlencode(App\Support\Telemedicine\TelemedicineCaseDocumentReadyNotification::EXPEDIENTE_DOCUMENTAL_TAB_QUERY));
});

it('no redirige cuando no se generó ningún documento', function (): void {
    $action = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineCases/Actions/RegenerateTelemedicineCaseDocumentsAction.php');

    expect($action)
        ->toContain('$livewire->redirect($redirectUrl)')
        // El aviso de fallo debe leerse donde está el médico, sin arrastrarlo a otra pantalla.
        ->toContain('! $result->noneGenerated() && filled($redirectUrl)')
        // Se reutiliza el enlace ya probado en lugar de rearmarlo a mano.
        ->toContain('TelemedicineCaseDocumentReadyNotification::caseExpedienteDocumentalUrl');
});
