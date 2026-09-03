<?php

declare(strict_types=1);

use App\Jobs\GeneratePdfInformeMedicoCorto;
use App\Jobs\GeneratePdfMedicamentos;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicinePatientMedications;
use App\Models\User;
use App\Support\Telemedicine\TelemedicineCaseDocumentRegenerationResult;
use App\Support\Telemedicine\TelemedicineCaseDocumentRegenerationService;
use App\Support\Telemedicine\TelemedicineCaseTdgReassignmentCoordination;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

uses(Tests\TestCase::class);

it('expone opciones segun datos del caso y consulta inicial', function (): void {
    $case = new TelemedicineCase(['code' => 'TM-1']);
    $case->id = 10;

    $service = new class extends TelemedicineCaseDocumentRegenerationService
    {
        public ?TelemedicineConsultationPatient $consultation = null;

        /** @var list<array{medicine: string, indications: string, duration: string}> */
        public array $medications = [];

        /** @var list<string> */
        public array $labs = [];

        /** @var list<string> */
        public array $studies = [];

        /** @var list<string> */
        public array $specialists = [];

        public function resolveConsultation(TelemedicineCase $case): ?TelemedicineConsultationPatient
        {
            return $this->consultation;
        }

        protected function medicationsForCase(TelemedicineCase $case): \Illuminate\Support\Collection
        {
            return collect($this->medications)->map(
                fn (array $row): TelemedicinePatientMedications => new TelemedicinePatientMedications($row)
            );
        }

        protected function labsForCase(TelemedicineCase $case): array
        {
            return $this->labs;
        }

        protected function studiesForCase(TelemedicineCase $case): array
        {
            return $this->studies;
        }

        protected function specialistsForCase(TelemedicineCase $case): array
        {
            return $this->specialists;
        }
    };

    expect($service->availableOptions($case))->toBe([]);

    $consultation = new TelemedicineConsultationPatient([
        'status' => 'CONSULTA INICIAL',
        'reason_consultation' => 'DOLOR',
        'code_reference' => 'REF-1',
        'telemedicine_service_list_id' => 1,
    ]);
    $consultation->id = 5;
    $service->consultation = $consultation;

    $options = $service->availableOptions($case);

    expect($options)
        ->toHaveKey(TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_CORTO)
        ->toHaveKey(TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_LARGO)
        ->not->toHaveKey(TelemedicineCaseDocumentRegenerationService::DOCUMENT_MEDICAMENTOS);

    $consultation->telemedicine_service_list_id = TelemedicineCaseTdgReassignmentCoordination::AMD_SERVICE_LIST_ID;
    $service->medications = [['medicine' => 'SUERO', 'indications' => '1', 'duration' => '3']];
    $service->labs = ['HEMOGRAMA'];
    $service->studies = ['RX TORAX'];
    $service->specialists = ['CARDIOLOGIA'];

    $options = $service->availableOptions($case);

    expect($options)
        ->toHaveKey(TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_CORTO)
        ->not->toHaveKey(TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_LARGO)
        ->toHaveKey(TelemedicineCaseDocumentRegenerationService::DOCUMENT_MEDICAMENTOS)
        ->toHaveKey(TelemedicineCaseDocumentRegenerationService::DOCUMENT_LABORATORIOS)
        ->toHaveKey(TelemedicineCaseDocumentRegenerationService::DOCUMENT_IMAGENOLOGIA)
        ->toHaveKey(TelemedicineCaseDocumentRegenerationService::DOCUMENT_ESPECIALISTA);
});

/**
 * Servicio real con solo las fronteras de I/O sustituidas: la consulta, el
 * médico, el paciente y la ejecución del job. Todo lo demás —selección,
 * armado de payloads y control de fallos— es el código de producción.
 */
function telemedicineRegenerationServiceForTest(?callable $onRun = null): TelemedicineCaseDocumentRegenerationService
{
    return new class($onRun) extends TelemedicineCaseDocumentRegenerationService
    {
        /** @var list<object> */
        public array $executed = [];

        public function __construct(private $onRun = null) {}

        public function resolveConsultation(TelemedicineCase $case): ?TelemedicineConsultationPatient
        {
            $consultation = new TelemedicineConsultationPatient([
                'status' => 'CONSULTA INICIAL',
                'reason_consultation' => 'FIEBRE',
                'code_reference' => 'REF-22',
                'telemedicine_service_list_id' => 1,
            ]);
            $consultation->id = 22;

            return $consultation;
        }

        protected function resolveDoctor(TelemedicineConsultationPatient $consultation, TelemedicineCase $case): ?TelemedicineDoctor
        {
            $doctor = new TelemedicineDoctor(['name' => 'CAROLINA PINILLO']);
            $doctor->id = 5;

            return $doctor;
        }

        protected function resolvePatient(TelemedicineConsultationPatient $consultation, TelemedicineCase $case): ?TelemedicinePatient
        {
            $patient = new TelemedicinePatient(['nro_identificacion' => '123']);
            $patient->id = 7;

            return $patient;
        }

        protected function medicationsForCase(TelemedicineCase $case): \Illuminate\Support\Collection
        {
            return collect([
                new TelemedicinePatientMedications([
                    'medicine' => 'PARACETAMOL',
                    'indications' => '1 CADA 8H',
                    'duration' => '3 DIAS',
                ]),
            ]);
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

    $result = $service->regenerate(telemedicineRegenerationTestCase(), [
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_CORTO,
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_MEDICAMENTOS,
    ], telemedicineRegenerationTestUser());

    expect($result->generated)->toBe([
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_CORTO,
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_MEDICAMENTOS,
    ])
        ->and($result->failed)->toBe([])
        ->and($result->allGenerated())->toBeTrue()
        ->and($service->executed)->toHaveCount(2)
        ->and($service->executed[0])->toBeInstanceOf(GeneratePdfInformeMedicoCorto::class)
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

    $result = $service->regenerate(telemedicineRegenerationTestCase(), [
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_CORTO,
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_MEDICAMENTOS,
    ], telemedicineRegenerationTestUser());

    expect($result->generated)->toBe([TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_CORTO])
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

    $result = $service->regenerate(telemedicineRegenerationTestCase(), [
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_CORTO,
    ], telemedicineRegenerationTestUser());

    expect($result->noneGenerated())->toBeTrue()
        ->and($result->generatedCount())->toBe(0)
        ->and($result->failedCount())->toBe(1);
});

it('rechaza una selección vacía o inexistente', function (): void {
    $service = telemedicineRegenerationServiceForTest();

    expect(fn () => $service->regenerate(telemedicineRegenerationTestCase(), [], telemedicineRegenerationTestUser()))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $service->regenerate(telemedicineRegenerationTestCase(), ['documento-inventado'], telemedicineRegenerationTestUser()))
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
        ->toContain('TelemedicineCaseDocumentRegenerationService')
        ->toContain('bulkToggleable');

    expect($dash)->toContain('RegenerateTelemedicineCaseDocumentsAction::make');
    expect($table)->toContain('RegenerateTelemedicineCaseDocumentsAction::make');

    expect($service)
        ->toContain('GeneratePdfInformeMedicoCorto')
        ->toContain('GeneratePdfMedicamentos')
        ->toContain('GeneratePdfLaboratorio')
        ->toContain('GeneratePdfImagenologia')
        ->toContain('GeneratePdfEspecialista')
        ->toContain('dispatch_sync')
        ->not->toContain("->onQueue('telemedicina')")
        ->toContain('labsSplitForCase')
        ->toContain('TelemedicineMedicationCoverage::isCovered');
});

it('el resultado distingue éxito total, parcial y fallo completo', function (): void {
    $labels = [
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_CORTO => 'Informe médico',
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_MEDICAMENTOS => 'Récipe',
    ];

    $todo = new TelemedicineCaseDocumentRegenerationResult(array_keys($labels), [], $labels);
    $parcial = new TelemedicineCaseDocumentRegenerationResult(
        [TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_CORTO],
        [TelemedicineCaseDocumentRegenerationService::DOCUMENT_MEDICAMENTOS => 'Disco lleno'],
        $labels,
    );
    $ninguno = new TelemedicineCaseDocumentRegenerationResult(
        [],
        [TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_CORTO => 'Error'],
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
