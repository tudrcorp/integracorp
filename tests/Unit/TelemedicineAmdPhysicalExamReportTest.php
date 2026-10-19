<?php

declare(strict_types=1);

use App\Jobs\GeneratePdfInformeMedicoLargo;
use App\Jobs\GeneratePdfInformeSeguimiento;
use App\Models\TelemedicineAmdPhysicalExam;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\User;
use App\Support\Telemedicine\TelemedicineAmdBitacoraCatalog;
use App\Support\Telemedicine\TelemedicineAmdFileRegistrar;
use App\Support\Telemedicine\TelemedicineAmdPhysicalExamRegistrar;
use App\Support\Telemedicine\TelemedicineAmdPhysicalExamReport;
use App\Support\Telemedicine\TelemedicineAmdPhysicalExamTemplate;
use App\Support\Telemedicine\TelemedicineInformePdfRenderer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

/*
 * El examen físico AMD se anexa al informe de la consulta en que se hizo la
 * visita: Informe Médico en la consulta inicial, Informe de Seguimiento en las
 * demás. Escribe en la base solo dentro de una transacción revertida.
 */

beforeEach(function (): void {
    DB::beginTransaction();
    session()->forget(TelemedicineAmdPhysicalExamRegistrar::SESSION_PENDING_EXAM_ID);
    // created_by / updated_by tienen FK a users: se actúa como un usuario real (sólo lectura).
    $this->usuario = User::query()->orderBy('id')->firstOrFail();
    $this->actingAs($this->usuario);
});

afterEach(fn () => DB::rollBack());

/**
 * Consulta real con médico y contenido para emitir su informe, en la etapa pedida.
 */
function consultaConInforme(bool $inicial): ?TelemedicineConsultationPatient
{
    return TelemedicineConsultationPatient::query()
        ->whereNotNull('telemedicine_case_id')
        ->whereNotNull('telemedicine_patient_id')
        ->where('telemedicine_doctor_id', '>', 0)
        ->whereNotNull('diagnostic_impression')
        ->when(
            $inicial,
            fn ($query) => $query->where('status', 'CONSULTA INICIAL'),
            fn ($query) => $query->where('status', 'EN SEGUIMIENTO'),
        )
        ->whereHas('telemedicineCase')
        ->latest('id')
        ->first();
}

/**
 * @return array{vitals: list<array{label: string, value: string}>, systems: list<array{label: string, text: string, is_default: bool}>}
 */
function examenDePrueba(): array
{
    return TelemedicineAmdPhysicalExamTemplate::display(new TelemedicineAmdPhysicalExam([
        ...TelemedicineAmdPhysicalExamTemplate::defaults(),
        'heart_rate' => '92',
        'thorax' => 'Crepitantes en base izquierda.',
    ]));
}

/**
 * @return array<string, mixed>
 */
function datosDeInforme(array $extra = []): array
{
    return [
        'fecha' => '19/10/2026',
        'code_reference' => 'REF-AMD',
        'name_patient' => 'PACIENTE DE PRUEBA',
        'ci_patient' => '12345678',
        'age_patient' => '40',
        'reason' => 'DOLOR',
        'actual_phatology' => 'CERVICALGIA',
        'diagnostic_impression' => 'CERVICALGIA CRÓNICA',
        'current_illness_history' => 'HISTORIA',
        'patient_evolution' => 'EVOLUCIONA FAVORABLEMENTE',
        'doctor_name' => 'MÉDICO DE PRUEBA',
        'code_mpps' => '123456',
        ...$extra,
    ];
}

/*
 * ---------------------------------------------------------------------------
 * A qué informe va
 * ---------------------------------------------------------------------------
 */

it('el examen va al Informe Médico en la consulta inicial y al de Seguimiento en las demás', function (?string $status, ?string $informe): void {
    expect(TelemedicineAmdPhysicalExamReport::labelForStatus($status))->toBe($informe);
})->with([
    'consulta inicial' => ['CONSULTA INICIAL', 'Informe Médico'],
    'seguimiento' => ['EN SEGUIMIENTO', 'Informe de Seguimiento'],
    'alta médica' => ['ALTA MEDICA', 'Informe de Seguimiento'],
    'con espacios' => ['  CONSULTA INICIAL ', 'Informe Médico'],
    'etapa desconocida' => ['', null],
    'sin etapa' => [null, null],
]);

/*
 * ---------------------------------------------------------------------------
 * De dónde sale el examen del informe
 * ---------------------------------------------------------------------------
 */

it('respeta el examen que ya traen los datos, aunque sea vacío', function (): void {
    $examen = examenDePrueba();

    expect(TelemedicineAmdPhysicalExamReport::forInformeData(['physical_exam' => $examen]))->toBe($examen)
        ->and(TelemedicineAmdPhysicalExamReport::forInformeData(['physical_exam' => null, 'telemedicine_consultation_id' => 999_999_999]))->toBeNull();
});

it('busca el examen por la consulta del informe', function (): void {
    $consulta = consultaConInforme(inicial: false) ?? $this->markTestSkipped('No hay consultas de seguimiento.');

    TelemedicineAmdPhysicalExam::query()->where('telemedicine_consultation_patient_id', $consulta->id)->delete();
    TelemedicineAmdPhysicalExam::query()->create([
        ...TelemedicineAmdPhysicalExamTemplate::defaults(),
        'heart_rate' => '92',
        'telemedicine_case_id' => $consulta->telemedicine_case_id,
        'telemedicine_patient_id' => $consulta->telemedicine_patient_id,
        'telemedicine_consultation_patient_id' => $consulta->id,
    ]);

    $examen = TelemedicineAmdPhysicalExamReport::forInformeData(['telemedicine_consultation_id' => $consulta->id]);

    expect($examen['vitals'][0])->toBe(['label' => 'Frecuencia cardíaca', 'value' => '92 lpm'])
        ->and($examen['systems'])->toHaveCount(12);
});

it('nunca toma el examen pendiente de otra consulta del mismo caso', function (): void {
    $consulta = consultaConInforme(inicial: false) ?? $this->markTestSkipped('No hay consultas de seguimiento.');

    TelemedicineAmdPhysicalExam::query()->where('telemedicine_consultation_patient_id', $consulta->id)->delete();
    // Un examen pendiente (de otra visita aún sin guardar) del mismo caso.
    TelemedicineAmdPhysicalExam::query()->create([
        ...TelemedicineAmdPhysicalExamTemplate::defaults(),
        'telemedicine_case_id' => $consulta->telemedicine_case_id,
        'telemedicine_patient_id' => $consulta->telemedicine_patient_id,
        'telemedicine_consultation_patient_id' => null,
    ]);

    expect(TelemedicineAmdPhysicalExamReport::forInformeData(['telemedicine_consultation_id' => $consulta->id]))->toBeNull()
        ->and(TelemedicineAmdPhysicalExamReport::forInformeData(['telemedicine_case_id' => $consulta->telemedicine_case_id]))->toBeNull();
});

it('el renderizador de informes resuelve el examen en el paso que comparten todos', function (): void {
    $renderer = file_get_contents(dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineInformePdfRenderer.php');

    expect($renderer)->toContain("\$data['physical_exam'] = TelemedicineAmdPhysicalExamReport::forInformeData(\$data);")
        ->and(strpos($renderer, 'forInformeData'))->toBeLessThan(strpos($renderer, 'Pdf::loadView'));
});

/*
 * ---------------------------------------------------------------------------
 * El informe lo pinta
 * ---------------------------------------------------------------------------
 */

it('el informe de seguimiento anexa el examen después de la evolución del paciente', function (): void {
    $html = view(TelemedicineInformePdfRenderer::VIEW_SEGUIMIENTO, ['data' => datosDeInforme(['physical_exam' => examenDePrueba()])])->render();

    expect($html)->toContain('Examen físico · Atención médica domiciliaria (AMD)')
        ->toContain('92 lpm')
        ->toContain('Crepitantes en base izquierda.')
        ->and(strpos($html, 'Evolución del paciente'))->toBeLessThan(strpos($html, 'Examen físico · Atención médica'));
});

it('el informe médico anexa el examen antes de la impresión diagnóstica', function (): void {
    $html = view(TelemedicineInformePdfRenderer::VIEW_LARGO, ['data' => datosDeInforme(['physical_exam' => examenDePrueba()])])->render();

    expect($html)->toContain('Examen físico · Atención médica domiciliaria (AMD)')
        ->and(strpos($html, 'Examen físico · Atención médica'))->toBeLessThan(strpos($html, 'Impresión diagnóstica'));
});

it('sin examen el informe no trae la sección', function (string $view): void {
    $html = view($view, ['data' => datosDeInforme(['physical_exam' => null])])->render();

    expect($html)->not->toContain('Examen físico');
})->with([
    'informe médico' => [TelemedicineInformePdfRenderer::VIEW_LARGO],
    'informe de seguimiento' => [TelemedicineInformePdfRenderer::VIEW_SEGUIMIENTO],
]);

it('el título del examen va en el encabezado de su tabla para no quedar solo al pie de una página', function (): void {
    $html = view('documents.partials.informe-examen-fisico-amd', ['exam' => examenDePrueba()])->render();

    preg_match('/<thead>.*?<\/thead>/s', $html, $encabezado);

    expect($encabezado[0] ?? '')->toMatch('/<th colspan="2"[^>]*>Examen físico · Atención médica domiciliaria \(AMD\)<\/th>/')
        // Fila medidora: DomPDF toma los anchos de la primera fila e ignora <colgroup>.
        ->toMatch('/<tr>\s*<th style="width: 24%;[^"]*"><\/th>\s*<th style="width: 76%;[^"]*"><\/th>\s*<\/tr>/');
});

it('genera el PDF de seguimiento con el examen por la vía real', function (): void {
    $pdf = TelemedicineInformePdfRenderer::render(
        TelemedicineInformePdfRenderer::VIEW_SEGUIMIENTO,
        datosDeInforme(['physical_exam' => examenDePrueba()]),
    );

    expect($pdf)->toStartWith('%PDF')
        ->and(strlen($pdf))->toBeGreaterThan(8_000);
});

/*
 * ---------------------------------------------------------------------------
 * Al guardar el examen, el informe se actualiza
 * ---------------------------------------------------------------------------
 */

it('encola el informe que corresponde a la etapa de la consulta', function (bool $inicial, string $job, string $informe): void {
    $consulta = consultaConInforme($inicial) ?? $this->markTestSkipped('No hay consultas en esa etapa.');

    Queue::fake();

    $encolado = TelemedicineAmdPhysicalExamReport::queueReportRefresh($consulta, $this->usuario);

    expect($encolado)->toBe($informe);
    // `data` es protegida en los jobs: se lee desde su propio ámbito.
    Queue::assertPushed($job, fn (object $pushed): bool => (int) ((fn (): array => (array) $this->data)->call($pushed)['telemedicine_consultation_id'] ?? 0) === (int) $consulta->id);
    Queue::assertPushed($job, 1);
})->with([
    'consulta inicial' => [true, GeneratePdfInformeMedicoLargo::class, 'Informe Médico'],
    'seguimiento' => [false, GeneratePdfInformeSeguimiento::class, 'Informe de Seguimiento'],
]);

it('no encola nada si la consulta no tiene médico que firme', function (): void {
    $consulta = consultaConInforme(inicial: true) ?? $this->markTestSkipped('No hay consultas.');
    $consulta->telemedicine_doctor_id = null;

    Queue::fake();

    expect(TelemedicineAmdPhysicalExamReport::queueReportRefresh($consulta, $this->usuario))->toBeNull();
    Queue::assertNothingPushed();
});

it('el modal del examen dice a qué informe se anexa y avisa si no se pudo actualizar', function (): void {
    $trait = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Concerns/HasAmdPhysicalExamModal.php');
    $tarjeta = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/telemedicina/consultations/inform-amd-trigger.blade.php');

    expect($trait)
        ->toContain('Este examen se anexa al <strong>')
        ->toContain('Al guardar, el informe se actualiza solo con el examen incluido.')
        ->toContain('Estamos actualizando el {$report} con el examen anexado.')
        ->toContain('Use «Generar documentos» en el caso para incluirlo.')
        ->toContain('TelemedicineAmdPhysicalExamReport::queueReportRefresh')
        ->not->toContain('Informe Médico Largo')
        ->and($tarjeta)
        ->toContain('amdVisitProgress()')
        ->not->toContain('Informe Médico Largo');

    // La tarjeta toma el destino del examen del avance de la visita.
    $pasos = (new \App\Support\Telemedicine\TelemedicineAmdVisitProgress(false, false, 0, 'Informe de Seguimiento'))->steps();

    expect($pasos[1]['detail'])->toBe('Pendiente · se anexará al Informe de Seguimiento')
        ->and((new \App\Support\Telemedicine\TelemedicineAmdVisitProgress(false, true, 0, 'Informe Médico'))->steps()[1]['detail'])
        ->toBe('Cargado · se anexa al Informe Médico');
});

/*
 * ---------------------------------------------------------------------------
 * Archivos AMD en la pestaña «Bitácoras AMD»
 * ---------------------------------------------------------------------------
 */

/**
 * Caso en memoria con dos consultas: no toca la base.
 */
function casoConArchivosAmd(): TelemedicineCase
{
    $inicial = new TelemedicineConsultationPatient(['code_reference' => 'REF-INICIAL']);
    $inicial->id = 1;
    $inicial->uploaded_documents = [
        [
            'document_name' => 'foto-herida.jpg',
            'file_path' => 'telemedicina-doc/foto-herida.jpg',
            'document_types' => [TelemedicineAmdFileRegistrar::DOCUMENT_TYPE_LABEL],
            'uploaded_at' => '2026-10-18 09:00:00',
        ],
        [
            'document_name' => '123-REF-INICIAL-Informe-Médico.pdf',
            'file_path' => 'telemedicina-doc/123-REF-INICIAL-Informe-Médico.pdf',
            'document_types' => ['INFORME MEDICO'],
            'uploaded_at' => '2026-10-18 09:05:00',
        ],
    ];

    $seguimiento = new TelemedicineConsultationPatient(['code_reference' => null]);
    $seguimiento->id = 2;
    $seguimiento->uploaded_documents = [
        [
            'document_name' => 'electro.pdf',
            'file_path' => 'telemedicina-doc/electro.pdf',
            'document_types' => [TelemedicineAmdFileRegistrar::DOCUMENT_TYPE_LABEL],
            'uploaded_at' => '2026-10-19 11:30:00',
        ],
        'entrada corrupta',
    ];

    $caso = new TelemedicineCase(['code' => 'TLM-AMD']);
    $caso->id = 77;
    $caso->setRelation('amdInforms', collect());
    $caso->setRelation('consultations', collect([$inicial, $seguimiento]));

    return $caso;
}

it('la pestaña lista solo los archivos AMD, del más reciente al más antiguo', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('telemedicina-doc/foto-herida.jpg', 'x');

    $archivos = TelemedicineAmdBitacoraCatalog::files(casoConArchivosAmd());

    expect($archivos)->toHaveCount(2)
        ->and($archivos[0]['name'])->toBe('electro.pdf')
        ->and($archivos[0]['consultation_reference'])->toBe('CONS-2')
        ->and($archivos[0]['is_image'])->toBeFalse()
        ->and($archivos[0]['document_exists'])->toBeFalse()
        ->and($archivos[0]['download_url'])->toBeNull()
        ->and($archivos[1]['name'])->toBe('foto-herida.jpg')
        ->and($archivos[1]['consultation_reference'])->toBe('REF-INICIAL')
        ->and($archivos[1]['is_image'])->toBeTrue()
        ->and($archivos[1]['document_exists'])->toBeTrue()
        ->and($archivos[1]['uploaded_at_label'])->toBe('18/10/2026 09:00');
});

it('la pestaña muestra los archivos AMD aunque el caso no tenga informes AMD', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('telemedicina-doc/foto-herida.jpg', 'x');

    $contexto = TelemedicineAmdBitacoraCatalog::viewContext(casoConArchivosAmd());
    $html = view('filament.telemedicina.cases.amd-bitacora', $contexto)->render();

    expect($contexto['filesTotal'])->toBe(2)
        ->and($contexto['withDocument'])->toBe(1)
        ->and($html)->toContain('Archivos AMD')
        ->toContain('foto-herida.jpg')
        ->toContain('Ver imagen')
        ->toContain('electro.pdf')
        ->toContain('No encontrado')
        ->not->toContain('Sin bitácoras AMD registradas');
});

it('sin informes ni archivos la pestaña muestra el estado vacío', function (): void {
    $caso = new TelemedicineCase(['code' => 'TLM-VACIO']);
    $caso->id = 78;
    $caso->setRelation('amdInforms', collect());
    $caso->setRelation('consultations', collect());

    $html = view('filament.telemedicina.cases.amd-bitacora', TelemedicineAmdBitacoraCatalog::viewContext($caso))->render();

    expect($html)->toContain('Sin bitácoras AMD registradas')
        ->toContain('Los informes y archivos cargados desde la consulta AMD aparecerán aquí.')
        ->not->toContain('Imágenes y documentos que el médico cargó');
});

it('cargar un archivo AMD le dice al médico que también queda en la pestaña', function (): void {
    $modal = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Concerns/HasInformAmdModal.php');

    expect(substr_count($modal, 'pestaña «Bitácoras AMD»'))->toBe(3)
        ->and($modal)->not->toContain('Informe Médico Largo');
});
