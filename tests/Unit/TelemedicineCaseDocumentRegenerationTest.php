<?php

declare(strict_types=1);

use App\Jobs\GeneratePdfInformeMedicoCorto;
use App\Jobs\GeneratePdfMedicamentos;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicinePatientMedications;
use App\Models\User;
use App\Support\Telemedicine\TelemedicineCaseDocumentRegenerationService;
use App\Support\Telemedicine\TelemedicineCaseTdgReassignmentCoordination;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;

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

it('despacha batch de jobs para los documentos seleccionados', function (): void {
    Bus::fake();

    $case = new TelemedicineCase(['code' => 'TM-2']);
    $case->id = 20;

    $user = new User(['name' => 'Dr Test']);
    $user->id = 9;

    $regenerator = new class extends TelemedicineCaseDocumentRegenerationService
    {
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

        public function regenerate(TelemedicineCase $case, array $documentKeys, User $user): array
        {
            $documentKeys = array_values(array_unique(array_filter($documentKeys)));
            $available = $this->availableOptions($case);
            $selected = array_values(array_filter(
                $documentKeys,
                static fn (string $key): bool => array_key_exists($key, $available),
            ));

            $jobs = [];

            foreach ($selected as $documentKey) {
                $job = match ($documentKey) {
                    self::DOCUMENT_INFORME_CORTO => new GeneratePdfInformeMedicoCorto(
                        ['code_reference' => 'REF-22', 'ci_patient' => '123'],
                        $user,
                        self::DOCUMENT_INFORME_CORTO,
                    ),
                    self::DOCUMENT_MEDICAMENTOS => new GeneratePdfMedicamentos(
                        ['code_reference' => 'REF-22', 'ci_patiente' => '123', 'medicationsArr' => []],
                        $user,
                        self::DOCUMENT_MEDICAMENTOS,
                    ),
                    default => null,
                };

                if ($job !== null) {
                    $jobs[] = $job;
                }
            }

            Bus::batch($jobs)
                ->name('telemedicina-case-docs-regenerate-'.$case->id)
                ->onQueue('telemedicina')
                ->dispatch();

            return $selected;
        }
    };

    $selected = $regenerator->regenerate($case, [
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_CORTO,
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_MEDICAMENTOS,
    ], $user);

    expect($selected)->toBe([
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_CORTO,
        TelemedicineCaseDocumentRegenerationService::DOCUMENT_MEDICAMENTOS,
    ]);

    Bus::assertBatched(function (PendingBatch $batch) use ($case): bool {
        return str_contains((string) $batch->name, 'telemedicina-case-docs-regenerate-'.$case->id)
            && $batch->jobs->count() === 2
            && $batch->queue() === 'telemedicina';
    });
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
        ->toContain("->onQueue('telemedicina')");
});
