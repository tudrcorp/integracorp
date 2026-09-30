<?php

declare(strict_types=1);

use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Concerns\HasConsultationReviewStep;
use App\Support\Telemedicine\TelemedicineCaseTdgReassignmentCoordination;
use App\Support\Telemedicine\TelemedicineConsultationReview;
use App\Support\Telemedicine\TelemedicineConsultationWizardSteps;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function estadoConsultaInicial(array $extra = []): array
{
    return [
        'status' => 'CONSULTA INICIAL',
        'full_name' => 'ANA PRUEBA',
        'nro_identificacion' => '12345678',
        'age' => '34',
        'telemedicine_case_code' => '11111-0001',
        'pa' => '120/80',
        'fc' => '80',
        'reason_consultation' => 'Fiebre',
        'diagnostic_impression' => 'Faringitis aguda',
        'complements' => [],
        'feedbackOne' => false,
        ...$extra,
    ];
}

/**
 * @param  array<string, mixed>  $review
 * @return array<string, mixed>|null
 */
function seccionRevision(array $review, string $title): ?array
{
    return collect($review['sections'])->firstWhere('title', $title);
}

/*
 * ---------------------------------------------------------------------------
 * Contenido de la revisión
 * ---------------------------------------------------------------------------
 */

it('una consulta inicial muestra paciente, motivo, diagnóstico y signos vitales', function (): void {
    $review = TelemedicineConsultationReview::build(estadoConsultaInicial());

    expect($review['kind'])->toBe('Consulta inicial')
        ->and(array_column($review['sections'], 'title'))->toBe(['Paciente', 'Motivo y diagnóstico'])
        ->and(seccionRevision($review, 'Paciente')['fields'])->toMatchArray(['Paciente' => 'ANA PRUEBA', 'Edad' => '34 años', 'Caso' => '11111-0001'])
        ->and(seccionRevision($review, 'Motivo y diagnóstico')['fields'])->toMatchArray([
            'Signos vitales' => 'PA 120/80 · FC 80',
            'Motivo de consulta' => 'Fiebre',
            'Impresión diagnóstica' => 'Faringitis aguda',
        ])
        ->and(seccionRevision($review, 'Motivo y diagnóstico')['step'])->toBe(TelemedicineConsultationWizardSteps::REASON)
        ->and($review['warnings'])->toBe([]);
});

it('un seguimiento muestra el cuestionario en lugar del motivo', function (): void {
    $review = TelemedicineConsultationReview::build([
        'status' => 'EN SEGUIMIENTO',
        'cuestion_1' => 'Mejor',
        'patient_evolution' => 'Afebril',
        'complements' => [],
    ]);

    expect($review['kind'])->toBe('Seguimiento')
        ->and(seccionRevision($review, 'Cuestionario de seguimiento')['fields'])->toMatchArray(['Evolución' => 'Afebril', 'Cómo se siente' => 'Mejor'])
        ->and(seccionRevision($review, 'Cuestionario de seguimiento')['step'])->toBe(TelemedicineConsultationWizardSteps::FOLLOW_UP)
        ->and(seccionRevision($review, 'Motivo y diagnóstico'))->toBeNull()
        ->and($review['warnings'])->toBe([]);
});

it('separa lo cubierto de lo no cubierto y avisa el cupo que usará', function (): void {
    $review = TelemedicineConsultationReview::build(estadoConsultaInicial([
        'complements' => [2, 3],
        'labs' => ['GLICEMIA'],
        'other_labs' => ['BUN'],
        'consult_specialist' => [],
        'other_specialist' => ['ALERGOLOGO'],
    ]));

    $labs = seccionRevision($review, 'Laboratorios y estudios');
    $especialistas = seccionRevision($review, 'Interconsulta con especialista');

    expect($labs['lists'])->toBe([
        ['title' => 'Laboratorios · Cubiertos', 'tone' => 'covered', 'items' => ['GLICEMIA']],
        ['title' => 'Laboratorios · No cubiertos', 'tone' => 'not_covered', 'items' => ['BUN']],
    ])->and($especialistas['lists'][0]['tone'])->toBe('not_covered')
        ->and(implode(' ', $review['notes']))->toContain('Laboratorio')
        // Lo no cubierto no consume cupo de especialista.
        ->and(implode(' ', $review['notes']))->not->toContain('Especialista');
});

it('avisa lo que falta: diagnóstico vacío o complemento marcado sin elegir nada', function (): void {
    $review = TelemedicineConsultationReview::build(estadoConsultaInicial([
        'diagnostic_impression' => '  ',
        'complements' => [1, 2],
        'medications' => [],
        'labs' => [],
    ]));

    expect($review['warnings'])->toContain('La impresión diagnóstica está vacía.')
        ->toContain('Medicamentos e indicaciones: Marcó medicamentos pero no cargó ninguno.')
        ->toContain('Laboratorios y estudios: Marcó este complemento pero no eligió ninguno.');
});

it('un alta médica no muestra indicaciones y lo explica', function (): void {
    $review = TelemedicineConsultationReview::build([
        'status' => 'EN SEGUIMIENTO',
        'feedbackOne' => true,
        'complements' => [1, 2],
        'labs' => ['GLICEMIA'],
    ]);

    expect($review['kind'])->toBe('Alta médica')
        ->and(array_column($review['sections'], 'title'))->not->toContain('Laboratorios y estudios')
        ->and(implode(' ', $review['notes']))->toContain('no se crean medicamentos');
});

it('en una AMD recuerda el examen físico y el informe pendientes', function (): void {
    $estado = estadoConsultaInicial(['telemedicine_service_list_id' => TelemedicineCaseTdgReassignmentCoordination::AMD_SERVICE_LIST_ID]);

    expect(TelemedicineConsultationReview::build($estado)['warnings'])
        ->toContain('Es una AMD y todavía no cargó el examen físico.')
        ->toContain('Es una AMD y todavía no registró el informe AMD.')
        ->and(TelemedicineConsultationReview::build($estado, ['amd_inform' => true, 'amd_exam' => true])['warnings'])->toBe([]);
});

it('los medicamentos muestran nombre, indicación, cantidad y duración', function (): void {
    $review = TelemedicineConsultationReview::build(estadoConsultaInicial([
        'complements' => [1],
        'medications' => [
            'fila-1' => ['medicines' => 'Acetaminofén 500 mg', 'indications' => 'c/8h', 'quantity' => 10, 'duration' => '5 días'],
            'fila-2' => ['medicines' => '', 'indications' => ''],
        ],
    ]));

    expect(seccionRevision($review, 'Medicamentos e indicaciones')['lists'][0]['items'][0])
        ->toStartWith('Acetaminofén 500 mg — c/8h · Cantidad: 10 · Duración: 5 días');
});

/*
 * ---------------------------------------------------------------------------
 * Vistas
 * ---------------------------------------------------------------------------
 */

it('la revisión trae botón Editar hacia cada paso y los avisos', function (): void {
    $html = view('filament.telemedicina.consultations.review.summary', [
        'review' => TelemedicineConsultationReview::build(estadoConsultaInicial(['diagnostic_impression' => ''])),
    ])->render();

    expect($html)->toContain('Consulta inicial')
        ->toContain('Revise antes de registrar')
        ->toContain('Editar')
        ->toContain(TelemedicineConsultationWizardSteps::PATIENT)
        ->toContain(TelemedicineConsultationWizardSteps::REASON);
});

it('el paso de revisión se arma bajo demanda y protege su contenido de Livewire', function (): void {
    $paso = view('filament.telemedicina.consultations.review.step')->render();
    $barra = view('filament.telemedicina.consultations.review.back-bar')->render();

    expect($paso)->toContain('$wire.consultationReviewHtml()')
        ->toContain('wire:ignore')
        ->toContain(TelemedicineConsultationWizardSteps::REVIEW)
        ->and($barra)->toContain('$wire.validateStepsBeforeReview()')
        ->toContain('$wire.consultationReviewVisited');
});

it('el asistente termina en el paso de revisión y cada paso tiene su regreso', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php');
    $crear = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php');
    $editar = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/EditTelemedicineConsultationPatient.php');

    $revision = strpos($form, "Step::make('Revisar y registrar')");

    expect(substr_count($form, 'self::returnToReviewBar(),'))->toBe(6)
        ->and(substr_count($form, 'isInheritable: false)'))->toBe(7)
        ->and($revision)->toBeGreaterThan(strpos($form, "Step::make('Interconsulta con Especialista')"))
        ->and(strpos($form, 'Step::make(', $revision + 1))->toBeFalse()
        ->and($crear)->toContain('use HasConsultationReviewStep;')
        ->and($editar)->toContain('use HasConsultationReviewStep;');
});

/*
 * ---------------------------------------------------------------------------
 * Validación al volver a la revisión (asistente real, componente anónimo)
 * ---------------------------------------------------------------------------
 */

function asistenteDePrueba(): Component
{
    return new class extends Component implements HasSchemas
    {
        use HasConsultationReviewStep;
        use InteractsWithSchemas;

        /** @var array<string, mixed>|null */
        public ?array $data = ['status' => 'CONSULTA INICIAL', 'reason_consultation' => null, 'complements' => []];

        public function form(Schema $schema): Schema
        {
            return $schema
                ->statePath('data')
                ->components([
                    Wizard::make([
                        Step::make('Motivo')
                            ->key(TelemedicineConsultationWizardSteps::REASON, isInheritable: false)
                            ->schema([TextInput::make('reason_consultation')->required()]),
                        Step::make('Revisión')
                            ->key(TelemedicineConsultationWizardSteps::REVIEW, isInheritable: false)
                            ->schema([]),
                    ]),
                ]);
        }

        public function render(): string
        {
            return '<div>{{ $this->form }}</div>';
        }
    };
}

it('al volver a la revisión con un paso incompleto lleva a ese paso y muestra el error', function (): void {
    Livewire::test(asistenteDePrueba()::class)
        ->call('validateStepsBeforeReview')
        ->assertReturned(TelemedicineConsultationWizardSteps::REASON)
        ->assertHasErrors(['data.reason_consultation']);
});

it('con los pasos completos vuelve a la revisión y limpia los errores', function (): void {
    Livewire::test(asistenteDePrueba()::class)
        ->set('data.reason_consultation', 'Fiebre')
        ->call('validateStepsBeforeReview')
        ->assertReturned(TelemedicineConsultationWizardSteps::REVIEW)
        ->assertHasNoErrors();
});

it('armar la revisión marca que el médico ya la vio y no escribe en la base', function (): void {
    DB::flushQueryLog();
    DB::enableQueryLog();

    $componente = Livewire::test(asistenteDePrueba()::class)
        ->set('data.reason_consultation', 'Fiebre')
        ->call('consultationReviewHtml');

    $escrituras = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => preg_match('/^\s*(insert|update|delete)\b/i', $query['query']) === 1)
        ->count();
    DB::disableQueryLog();

    expect($componente->get('consultationReviewVisited'))->toBeTrue()
        ->and($escrituras)->toBe(0);

    $componente->assertReturned(fn (string $html): bool => str_contains($html, 'Fiebre') && str_contains($html, 'Consulta inicial'));
});
