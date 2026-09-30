<?php

declare(strict_types=1);

use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineHistoryPatient;
use App\Models\TelemedicinePatient;
use App\Support\Telemedicine\LabImagingResultDocumentGroups;
use App\Support\Telemedicine\TelemedicinePatientCareHistory;
use App\Support\Telemedicine\TelemedicinePatientHistorySummary;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

/**
 * Historia clínica en memoria, con las relaciones de notas vacías o dadas.
 *
 * @param  array<string, mixed>  $attributes
 * @param  array<string, list<array{observations: string, created_at: string}>>  $notes
 */
function historiaDePrueba(array $attributes, array $notes = []): TelemedicineHistoryPatient
{
    $historia = new TelemedicineHistoryPatient;
    $historia->forceFill([
        'code' => 'HC-0001',
        'created_by' => 'DRA. PRUEBA',
        'created_at' => Carbon::parse('2026-08-01 10:00'),
        'updated_at' => Carbon::parse('2026-09-01 10:00'),
        ...$attributes,
    ]);

    foreach (['pathologicalHistories', 'noPathologicalHistories', 'surgicalHistories', 'familyHistories', 'gynecologicalHistories'] as $relacion) {
        $historia->setRelation($relacion, new EloquentCollection(array_map(
            static fn (array $nota): object => (object) ['observations' => $nota['observations'], 'created_at' => Carbon::parse($nota['created_at'])],
            $notes[$relacion] ?? [],
        )));
    }

    return $historia;
}

/**
 * @param  array<string, mixed>  $summary
 * @return array<string, mixed>
 */
function seccion(array $summary, string $titulo): array
{
    return collect($summary['sections'])->firstWhere('title', $titulo);
}

/*
 * ---------------------------------------------------------------------------
 * Resumen de historia clínica
 * ---------------------------------------------------------------------------
 */

it('sin historia clínica lo dice en lugar de mostrar campos vacíos', function (): void {
    $resumen = TelemedicinePatientHistorySummary::summarize(null);

    expect($resumen['exists'])->toBeFalse()
        ->and($resumen['alerts'])->toBe([])
        ->and($resumen['sections'])->toBe([]);
});

it('pone arriba alergias, enfermedades del paciente y medicación habitual', function (): void {
    $resumen = TelemedicinePatientHistorySummary::summarize(historiaDePrueba([
        'allergies' => ['Penicilina', 'AINES'],
        'observations_allergies' => 'Mariscos',
        'diabetes_app' => true,
        'input_diabetes_app' => 'Tipo 2 desde 2019',
        'tension_alta_app' => true,
        'vih_app' => true,
        'medications_supplements' => 'Metformina 850 mg c/12h',
    ]));

    expect($resumen['alerts'])->toBe([
        ['title' => 'Alergias', 'items' => ['Penicilina', 'AINES', 'Mariscos']],
        ['title' => 'Enfermedades del paciente', 'items' => ['Hipertensión arterial', 'Diabetes mellitus: Tipo 2 desde 2019', 'VIH']],
        ['title' => 'Medicación habitual', 'items' => ['Metformina 850 mg c/12h']],
    ]);
});

it('no mezcla antecedentes familiares con los personales', function (): void {
    $resumen = TelemedicinePatientHistorySummary::summarize(historiaDePrueba([
        'diabetes' => true,
        'input_diabetes' => 'Madre',
        'cancer' => true,
        'diabetes_app' => false,
    ]));

    expect(seccion($resumen, 'Antecedentes familiares')['items'])->toBe([
        ['label' => 'Diabetes mellitus', 'detail' => 'Madre'],
        ['label' => 'Cáncer', 'detail' => null],
    ])->and(seccion($resumen, 'Antecedentes personales patológicos')['items'])->toBe([])
        ->and(collect($resumen['alerts'])->pluck('title')->all())->not->toContain('Enfermedades del paciente');
});

it('muestra sólo lo registrado: hábitos activos, gineco con valores y notas fechadas', function (): void {
    $resumen = TelemedicinePatientHistorySummary::summarize(historiaDePrueba([
        'tabaco' => true,
        'alcohol' => false,
        'numero_embarazos' => 2,
        'numero_partos' => 0,
        'cesareas' => 1,
        'history_surgical' => 'Apendicectomía 2015',
    ], [
        'surgicalHistories' => [
            ['observations' => 'Colecistectomía', 'created_at' => '2026-09-10'],
            ['observations' => 'Hernia inguinal', 'created_at' => '2026-09-20'],
        ],
    ]));

    expect(seccion($resumen, 'Hábitos (no patológicos)')['items'])->toBe([['label' => 'Tabaquismo', 'detail' => null]])
        ->and(seccion($resumen, 'Antecedentes ginecológicos')['items'])->toBe([
            ['label' => 'Embarazos', 'detail' => '2'],
            ['label' => 'Cesáreas', 'detail' => '1'],
        ])
        ->and(seccion($resumen, 'Antecedentes quirúrgicos')['notes'])->toBe([
            'Apendicectomía 2015',
            '20/09/2026 — Hernia inguinal',
            '10/09/2026 — Colecistectomía',
        ])
        ->and(seccion($resumen, 'Medicamentos y suplementos')['notes'])->toBe([]);
});

it('sin datos clave no inventa alertas', function (): void {
    $resumen = TelemedicinePatientHistorySummary::summarize(historiaDePrueba(['allergies' => [], 'observations_allergies' => '  ']));

    expect($resumen['exists'])->toBeTrue()
        ->and($resumen['alerts'])->toBe([])
        ->and($resumen['meta'])->toMatchArray(['Nro. de historia' => 'HC-0001', 'Registrada por' => 'DRA. PRUEBA']);
});

/*
 * ---------------------------------------------------------------------------
 * Consultas y casos anteriores (datos reales, sólo lectura)
 * ---------------------------------------------------------------------------
 */

function pacienteConVariosCasos(): ?TelemedicinePatient
{
    return TelemedicinePatient::query()
        ->whereHas('telemedicineCases', null, '>=', 2)
        ->whereHas('telemedicineConsultationPatients', null, '>=', 3)
        ->latest('id')
        ->first();
}

it('agrupa las consultas del paciente por caso, del más reciente al más antiguo', function (): void {
    $paciente = pacienteConVariosCasos();

    if ($paciente === null) {
        $this->markTestSkipped('No hay pacientes con varios casos.');
    }

    $actual = (int) $paciente->telemedicineCases()->latest('id')->value('id');
    $casos = TelemedicinePatientCareHistory::consultationsByCase($paciente, $actual);
    $totalConsultas = $paciente->telemedicineConsultationPatients()->count();

    expect(collect($casos)->sum(fn (array $caso): int => count($caso['consultations'])))->toBe($totalConsultas)
        ->and(collect($casos)->where('is_current', true)->pluck('case_id')->all())->toBe(array_values(array_filter([$actual], fn (int $id): bool => collect($casos)->contains('case_id', $id))))
        ->and(collect($casos)->every(fn (array $caso): bool => in_array($caso['consultations'][0]['stage'], ['Consulta inicial', 'Alta médica'], true) || str_starts_with($caso['consultations'][0]['stage'], 'Seguimiento')))->toBeTrue();
});

it('separa lo indicado cubierto de lo no cubierto', function (): void {
    $consulta = TelemedicineConsultationPatient::query()
        ->whereNotNull('other_labs')
        ->where('other_labs', '<>', '[]')
        ->whereNotNull('labs')
        ->where('labs', '<>', '[]')
        ->first();

    if ($consulta === null) {
        $this->markTestSkipped('No hay consultas con laboratorios cubiertos y no cubiertos.');
    }

    $casos = TelemedicinePatientCareHistory::consultationsByCase($consulta->telemedicinePatient);
    $item = collect($casos)->flatMap(fn (array $caso): array => $caso['consultations'])
        ->first(fn (array $c): bool => $c['reference'] === ($consulta->code_reference ?: 'CONS-'.$consulta->id));

    expect(collect($item['covered'])->where('type', 'Laboratorio')->pluck('name')->all())->toBe(array_values(array_filter($consulta->labs)))
        ->and(collect($item['not_covered'])->where('type', 'Laboratorio')->pluck('name')->all())->toBe(array_values(array_filter($consulta->other_labs)));
});

it('lista los casos del paciente con su número de consultas y marca el actual', function (): void {
    $paciente = pacienteConVariosCasos();

    if ($paciente === null) {
        $this->markTestSkipped('No hay pacientes con varios casos.');
    }

    $actual = (int) $paciente->telemedicineCases()->latest('id')->value('id');
    $casos = TelemedicinePatientCareHistory::cases($paciente, $actual);

    expect($casos)->toHaveCount($paciente->telemedicineCases()->count())
        ->and(collect($casos)->where('is_current', true)->pluck('id')->all())->toBe([$actual])
        ->and(collect($casos)->sum('consultations'))->toBe(
            TelemedicineConsultationPatient::query()->whereIn('telemedicine_case_id', collect($casos)->pluck('id'))->count()
        );
});

it('colorea el estatus del caso según su etapa', function (?string $status, string $tone): void {
    expect(TelemedicinePatientCareHistory::statusTone($status))->toBe($tone);
})->with([
    ['ALTA MEDICA', 'success'],
    ['EN SEGUIMIENTO', 'warning'],
    ['CASO NEGADO', 'danger'],
    ['ASIGNADO', 'info'],
    [null, 'info'],
]);

/*
 * ---------------------------------------------------------------------------
 * Resultados de laboratorio e imagenología
 * ---------------------------------------------------------------------------
 */

it('agrupa los resultados por tipo y conserva el orden de llegada', function (): void {
    $grupos = LabImagingResultDocumentGroups::group([
        ['document_name' => 'eco.pdf', 'document_types' => ['INFORME DE ESTUDIO Y/O IMAGENOLOGIA'], 'services' => [], 'uploaded_at' => '2026-09-20 10:00:00'],
        ['document_name' => 'hematologia.pdf', 'document_types' => ['RESULTADOS DE LABORATORIO'], 'services' => [], 'uploaded_at' => '2026-09-19 08:30:00'],
        ['document_name' => 'orina.pdf', 'document_types' => [], 'services' => ['LABORATORIOS'], 'uploaded_at' => '2026-09-18 08:00:00'],
        ['document_name' => 'nota.pdf', 'document_types' => [], 'services' => [], 'uploaded_at' => null],
    ]);

    expect(array_column($grupos, 'title'))->toBe(['Laboratorio', 'Imagenología', 'Otros documentos'])
        ->and(array_column($grupos[0]['documents'], 'document_name'))->toBe(['hematologia.pdf', 'orina.pdf'])
        ->and($grupos[0]['documents'][0]['uploaded_label'])->toBe('19/09/2026 08:30 AM')
        ->and($grupos[2]['documents'][0]['uploaded_label'])->toBeNull();
});

/*
 * ---------------------------------------------------------------------------
 * Vistas
 * ---------------------------------------------------------------------------
 */

it('el resumen de historia muestra alertas, secciones y estados vacíos', function (): void {
    $html = view('filament.telemedicina.consultations.modals.clinical-history-summary', [
        'summary' => TelemedicinePatientHistorySummary::summarize(historiaDePrueba(['allergies' => ['Penicilina'], 'tabaco' => true])),
        'patient' => ['name' => 'ANA PRUEBA', 'age' => '34', 'sex' => 'FEMENINO'],
    ])->render();

    $vacio = view('filament.telemedicina.consultations.modals.clinical-history-summary', [
        'summary' => TelemedicinePatientHistorySummary::summarize(null),
        'patient' => ['name' => 'ANA PRUEBA', 'age' => null, 'sex' => null],
    ])->render();

    expect($html)->toContain('ANA PRUEBA')->toContain('34 años')->toContain('Alergias')->toContain('Penicilina')
        ->toContain('Tabaquismo')->toContain('Sin registros.')
        ->and($vacio)->toContain('aún no tiene historia clínica');
});

it('las consultas anteriores muestran caso actual, etapas y leyenda de cobertura', function (): void {
    $html = view('filament.telemedicina.consultations.modals.previous-consultations', ['cases' => [[
        'case_id' => 7,
        'code' => '11111-0001',
        'status' => 'EN SEGUIMIENTO',
        'is_current' => true,
        'opened_at' => '01/09/2026',
        'consultations' => [[
            'stage' => 'Consulta inicial', 'tone' => 'initial', 'date' => '01/09/2026 08:00 AM', 'doctor' => 'DR. X',
            'service' => 'TELEMEDICINA', 'reference' => 'REF-1', 'reason' => 'Fiebre', 'diagnosis' => 'Faringitis',
            'covered' => [['type' => 'Laboratorio', 'name' => 'HEMATOLOGIA']],
            'not_covered' => [['type' => 'Especialista', 'name' => 'ALERGOLOGO']],
        ]],
    ]]])->render();

    $vacio = view('filament.telemedicina.consultations.modals.previous-consultations', ['cases' => []])->render();

    expect($html)->toContain('Caso actual')->toContain('Consulta inicial')->toContain('Faringitis')
        ->toContain('HEMATOLOGIA')->toContain('ALERGOLOGO')->toContain('No cubierto')
        ->and($vacio)->toContain('no tiene consultas anteriores');
});

it('los casos del paciente permiten abrir los anteriores pero no el actual', function (): void {
    $caso = fn (int $id, bool $actual): array => [
        'id' => $id, 'code' => 'C-'.$id, 'status' => 'ALTA MEDICA', 'tone' => 'success', 'is_current' => $actual,
        'opened_at' => '01/09/2026', 'reason' => 'Cefalea', 'doctor' => 'DR. X', 'priority' => null, 'consultations' => 2,
    ];

    $html = view('filament.telemedicina.consultations.modals.previous-cases', ['cases' => [$caso(5, true), $caso(4, false)]])->render();

    expect($html)->toContain('Caso actual')
        ->toContain('openPreviousCase(4)')
        ->not->toContain('openPreviousCase(5)')
        ->toContain('2 consultas');
});

it('los resultados se muestran agrupados y con fecha de carga', function (): void {
    $html = view('filament.telemedicina.consultations.lab-imaging-results-preview', [
        'documents' => [[
            'document_name' => 'hematologia.pdf', 'file_path' => 'a/b.pdf', 'document_types' => ['RESULTADOS DE LABORATORIO'],
            'services' => [], 'source' => 'Operaciones', 'uploaded_at' => '2026-09-19 08:30:00', 'extension' => 'PDF',
            'preview_url' => 'https://example.test/b.pdf', 'is_pdf' => true, 'is_image' => false,
        ]],
        'embeddedInModal' => true,
    ])->render();

    expect($html)->toContain('Laboratorio')->toContain('19/09/2026 08:30 AM')->toContain('hematologia.pdf');
});

it('la pantalla de atención usa las fichas nuevas y protege la apertura de casos ajenos', function (): void {
    $pagina = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php');

    expect($pagina)
        ->toContain("'filament.telemedicina.consultations.modals.clinical-history-summary'")
        ->toContain("'filament.telemedicina.consultations.modals.previous-consultations'")
        ->toContain("'filament.telemedicina.consultations.modals.previous-cases'")
        ->toContain('->telemedicineCases()->whereKey($caseId)->first()')
        ->toContain("session()->put('historyCasesToDetails', \$case)")
        ->not->toContain("view('history-patient-infolist'")
        ->not->toContain("view('consultation-patient-table'")
        ->not->toContain("view('table-telemedicine-cases'");
});
