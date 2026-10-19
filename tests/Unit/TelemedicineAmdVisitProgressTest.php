<?php

declare(strict_types=1);

use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDocument;
use App\Support\Telemedicine\TelemedicineAmdFileRegistrar;
use App\Support\Telemedicine\TelemedicineAmdVisitProgress;
use App\Support\Telemedicine\TelemedicineCaseTdgReassignmentCoordination;
use App\Support\Telemedicine\TelemedicineConsultationReview;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/*
 * Avance de la visita AMD: la tarjeta, las notificaciones y la revisión le dicen
 * al médico qué lleva hecho y qué le falta. Obligatorios: Informe AMD y examen
 * físico. Opcional: archivos. Escribe en la base solo dentro de una transacción
 * revertida.
 */

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

/**
 * Componente mínimo que pinta la tarjeta AMD con un avance dado, igual que la
 * página de consulta (la vista lee `$this->amdVisitProgress()`).
 */
function amdVisitCard(TelemedicineAmdVisitProgress $progress): string
{
    $component = new class extends Component
    {
        public static ?TelemedicineAmdVisitProgress $progress = null;

        public function amdVisitProgress(): TelemedicineAmdVisitProgress
        {
            return self::$progress;
        }

        public function render(): string
        {
            return '<div>@include(\'filament.telemedicina.consultations.inform-amd-trigger\')</div>';
        }
    };

    $component::$progress = $progress;

    return Livewire::test($component::class)->html();
}

/*
 * ---------------------------------------------------------------------------
 * La cuenta
 * ---------------------------------------------------------------------------
 */

it('cuenta lo obligatorio y dice qué falta', function (bool $informe, bool $examen, int $hechas, string $mensaje): void {
    $progress = new TelemedicineAmdVisitProgress(hasInform: $informe, hasExam: $examen);

    expect($progress->completedRequired())->toBe($hechas)
        ->and($progress->isComplete())->toBe($hechas === 2)
        ->and($progress->percent())->toBe($hechas * 50)
        ->and($progress->nextStepMessage())->toBe($mensaje);
})->with([
    'nada hecho' => [false, false, 0, 'Le falta: el Informe AMD y el examen físico AMD.'],
    'solo el informe' => [true, false, 1, 'Le falta: el examen físico AMD.'],
    'solo el examen' => [false, true, 1, 'Le falta: el Informe AMD.'],
    'todo hecho' => [true, true, 2, 'Acciones AMD completas. Ya puede continuar con la consulta.'],
]);

it('los archivos son opcionales: no cuentan para completar ni faltan', function (): void {
    $sinArchivos = new TelemedicineAmdVisitProgress(hasInform: true, hasExam: true, files: 0);
    $conArchivos = new TelemedicineAmdVisitProgress(hasInform: false, hasExam: false, files: 3);

    expect($sinArchivos->isComplete())->toBeTrue()
        ->and($conArchivos->completedRequired())->toBe(0)
        ->and($conArchivos->nextStepMessage())->not->toContain('archivo');
});

it('describe cada paso, con el destino del examen y los archivos en singular o plural', function (int $archivos, string $detalle): void {
    $pasos = (new TelemedicineAmdVisitProgress(true, false, $archivos, 'Informe de Seguimiento'))->steps();

    expect(array_column($pasos, 'key'))->toBe(['inform', 'exam', 'files'])
        ->and($pasos[0])->toMatchArray(['detail' => 'Registrado', 'done' => true, 'optional' => false])
        ->and($pasos[1])->toMatchArray(['detail' => 'Pendiente · se anexará al Informe de Seguimiento', 'done' => false])
        ->and($pasos[2])->toMatchArray(['detail' => $detalle, 'done' => $archivos > 0, 'optional' => true]);
})->with([
    'ninguno' => [0, 'Opcional · ninguno cargado'],
    'uno' => [1, '1 archivo cargado'],
    'varios' => [3, '3 archivos cargados'],
]);

/*
 * ---------------------------------------------------------------------------
 * Archivos AMD
 * ---------------------------------------------------------------------------
 */

it('cuenta los archivos AMD de una consulta guardada por su etiqueta', function (): void {
    $consulta = new TelemedicineConsultationPatient;
    $consulta->uploaded_documents = [
        ['document_name' => 'foto.jpg', 'document_types' => [TelemedicineAmdFileRegistrar::DOCUMENT_TYPE_LABEL]],
        ['document_name' => 'informe.pdf', 'document_types' => ['INFORME MEDICO']],
        ['document_name' => '', 'document_types' => [TelemedicineAmdFileRegistrar::DOCUMENT_TYPE_LABEL]],
        'entrada corrupta',
        ['document_name' => 'electro.pdf', 'document_types' => [TelemedicineAmdFileRegistrar::DOCUMENT_TYPE_LABEL]],
    ];

    expect(TelemedicineAmdFileRegistrar::countForConsultation($consulta))->toBe(2);
});

it('cuenta los archivos pendientes solo si existen y son del caso', function (): void {
    $caso = TelemedicineCase::query()->orderBy('id')->first() ?? $this->markTestSkipped('No hay casos.');
    $otroCaso = TelemedicineCase::query()->whereKeyNot($caso->id)->orderBy('id')->first() ?? $this->markTestSkipped('Hace falta un segundo caso.');

    // Igual que TelemedicineAmdFileRegistrar: sin consulta todavía, el id va en 0.
    $propio = TelemedicineDocument::query()->create(['telemedicine_case_id' => $caso->id, 'telemedicine_patient_id' => (int) $caso->telemedicine_patient_id, 'telemedicine_consultation_id' => 0, 'name' => 'propio.jpg']);
    $ajeno = TelemedicineDocument::query()->create(['telemedicine_case_id' => $otroCaso->id, 'telemedicine_patient_id' => (int) $otroCaso->telemedicine_patient_id, 'telemedicine_consultation_id' => 0, 'name' => 'ajeno.jpg']);

    expect(TelemedicineAmdFileRegistrar::countPending([$propio->id, (string) $propio->id, $ajeno->id, 999_999_999, 0, 'x'], (int) $caso->id))->toBe(1)
        ->and(TelemedicineAmdFileRegistrar::countPending([], (int) $caso->id))->toBe(0);
});

/*
 * ---------------------------------------------------------------------------
 * Tarjeta «Visita AMD»
 * ---------------------------------------------------------------------------
 */

it('al empezar la tarjeta marca 0 de 2 y resalta Informar AMD como siguiente paso', function (): void {
    $html = amdVisitCard(new TelemedicineAmdVisitProgress(false, false, 0, 'Informe Médico'));

    expect($html)->toContain('Visita AMD')
        ->toContain('0 de 2 obligatorias')
        ->toContain('aria-valuenow="0"')
        ->toContain('style="width: 0%"')
        ->toContain('Le falta: el Informe AMD y el examen físico AMD.')
        ->toContain('Pendiente · se anexará al Informe Médico')
        ->toContain('Opcional · ninguno cargado')
        ->toContain('>Informar AMD<')
        ->toContain('>Examen físico AMD<')
        ->toContain('>Cargar Archivo AMD<')
        ->and($html)->toMatch("/wire:click=\"mountAction\\('informAmd'\\)\"[^>]*class=\"[^\"]*bg-sky-600/s")
        ->and($html)->not->toMatch("/wire:click=\"mountAction\\('amdPhysicalExam'\\)\"[^>]*class=\"[^\"]*bg-sky-600/s");
});

it('con el informe hecho avanza a la mitad y resalta el examen físico', function (): void {
    $html = amdVisitCard(new TelemedicineAmdVisitProgress(true, false, 0, 'Informe de Seguimiento'));

    expect($html)->toContain('1 de 2 obligatorias')
        ->toContain('style="width: 50%"')
        ->toContain('Informe AMD (registrado)')
        ->toContain('Le falta: el examen físico AMD.')
        ->and($html)->toMatch("/wire:click=\"mountAction\\('amdPhysicalExam'\\)\"[^>]*class=\"[^\"]*bg-sky-600/s");
});

it('con todo hecho la tarjeta pasa a verde y dice que puede continuar', function (): void {
    $html = amdVisitCard(new TelemedicineAmdVisitProgress(true, true, 2, 'Informe Médico'));

    expect($html)->toContain('2 de 2 obligatorias')
        ->toContain('style="width: 100%"')
        ->toContain('border-emerald-200/80')
        ->toContain('Acciones AMD completas. Ya puede continuar con la consulta.')
        ->toContain('Examen físico (cargado)')
        ->toContain('2 archivos cargados')
        ->toContain('Cargar otro archivo AMD')
        ->not->toContain('bg-sky-600 px-5');
});

/*
 * ---------------------------------------------------------------------------
 * Revisión y mensajes
 * ---------------------------------------------------------------------------
 */

it('la revisión solo muestra el recuadro AMD en una consulta AMD sin alta', function (mixed $servicio, bool $alta, bool $muestra): void {
    $review = TelemedicineConsultationReview::build([
        'status' => 'CONSULTA INICIAL',
        'telemedicine_service_list_id' => $servicio,
        'feedbackOne' => $alta,
    ], new TelemedicineAmdVisitProgress(true, false));

    expect($review['amd'] !== null)->toBe($muestra);
})->with([
    'AMD' => [TelemedicineCaseTdgReassignmentCoordination::AMD_SERVICE_LIST_ID, false, true],
    'AMD con alta médica' => [TelemedicineCaseTdgReassignmentCoordination::AMD_SERVICE_LIST_ID, true, false],
    'otro servicio' => [TelemedicineCaseTdgReassignmentCoordination::AMD_SERVICE_LIST_ID + 1, false, false],
]);

it('la revisión avisa lo pendiente de la AMD sin bloquear el registro', function (): void {
    $review = TelemedicineConsultationReview::build([
        'status' => 'CONSULTA INICIAL',
        'telemedicine_service_list_id' => TelemedicineCaseTdgReassignmentCoordination::AMD_SERVICE_LIST_ID,
    ], new TelemedicineAmdVisitProgress(true, false, 1, 'Informe Médico'));

    $html = view('filament.telemedicina.consultations.review.summary', ['review' => $review])->render();

    expect($html)->toContain('Acciones AMD · 1 de 2 obligatorias')
        ->toContain('Le falta: el examen físico AMD.')
        ->toContain('Puede registrar la consulta igual')
        ->toContain('1 archivo cargado');
});

it('cada acción AMD termina su mensaje con el siguiente paso', function (): void {
    $root = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Concerns/';
    $informe = (string) file_get_contents($root.'HasInformAmdModal.php');
    $examen = (string) file_get_contents($root.'HasAmdPhysicalExamModal.php');

    expect(substr_count($informe, "->body((\$consultation\n"))->toBe(2)
        ->and(substr_count($informe, '$this->amdVisitProgress()->nextStepMessage()'))->toBe(2)
        ->and(substr_count($examen, '$this->amdNextStepMessage()'))->toBe(2);
});
