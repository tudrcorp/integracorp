<?php

declare(strict_types=1);

use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDoctor;
use App\Support\Operations\TelemedicineCaseBitacora;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

/**
 * Consulta en memoria: el resumen no necesita tocar la base.
 *
 * @param  array<string, mixed>  $attributes
 */
function consultaDePrueba(int $id, string $fecha, ?string $status, array $attributes = [], string $medico = 'DRA. PRUEBA'): TelemedicineConsultationPatient
{
    $consulta = new TelemedicineConsultationPatient;
    $consulta->forceFill([
        'id' => $id,
        'status' => $status,
        'code_reference' => 'CONS-'.$id,
        'created_at' => Carbon::parse($fecha),
        ...$attributes,
    ]);

    $doctor = new TelemedicineDoctor;
    $doctor->forceFill(['full_name' => $medico]);
    $consulta->setRelation('telemedicineDoctor', $doctor);

    return $consulta;
}

/**
 * Id de caso sin seguimientos en `telemedicine_follow_ups`.
 */
const CASO_SIN_SEGUIMIENTOS = 0;

it('cuenta la historia en orden: consulta inicial, seguimientos y alta', function (): void {
    $resumen = TelemedicineCaseBitacora::clinicalSummary(collect([
        consultaDePrueba(3, '2026-09-10 09:00', 'EN SEGUIMIENTO', ['patient_evolution' => 'Mejoría parcial']),
        consultaDePrueba(4, '2026-09-15 09:00', 'ALTA MEDICA', ['patient_evolution' => 'Asintomático']),
        consultaDePrueba(1, '2026-09-01 08:00', 'CONSULTA INICIAL', ['reason_consultation' => 'Fiebre']),
        consultaDePrueba(2, '2026-09-05 10:30', 'EN SEGUIMIENTO'),
    ]), CASO_SIN_SEGUIMIENTOS);

    expect(array_column($resumen, 'stage'))->toBe(['Consulta inicial', 'Seguimiento 1', 'Seguimiento 2', 'Alta médica'])
        ->and(array_column($resumen, 'tone'))->toBe(['initial', 'follow_up', 'follow_up', 'discharge'])
        ->and($resumen[0]['date'])->toBe('01/09/2026 08:00')
        ->and($resumen[0]['doctor'])->toBe('DRA. PRUEBA')
        ->and($resumen[0]['reference'])->toBe('CONS-1')
        ->and($resumen[0]['fields'])->toBe(['Motivo' => 'Fiebre'])
        ->and($resumen[3]['fields'])->toBe(['Evolución' => 'Asintomático']);
});

it('los seguimientos y el alta muestran las respuestas del cuestionario', function (): void {
    $resumen = TelemedicineCaseBitacora::clinicalSummary(collect([
        consultaDePrueba(1, '2026-09-01 08:00', 'CONSULTA INICIAL', ['reason_consultation' => 'Cefalea']),
        consultaDePrueba(2, '2026-09-04 08:00', 'EN SEGUIMIENTO', [
            'cuestion_1' => 'Mejor',
            'cuestion_2' => 'Buena respuesta',
            'cuestion_3' => 'Sí',
            'cuestion_4' => 'Pendiente hematología',
            'cuestion_5' => 'Se mantiene tratamiento',
        ]),
        consultaDePrueba(3, '2026-09-08 08:00', 'ALTA MEDICA', ['cuestion_1' => 'Sin molestias', 'observations' => 'Alta por mejoría']),
    ]), CASO_SIN_SEGUIMIENTOS);

    expect($resumen[1]['fields'])->toBe([
        'Cómo se siente' => 'Mejor',
        'Respuesta al tratamiento' => 'Buena respuesta',
        'Mejoría de síntomas' => 'Sí',
        'Estudios realizados' => 'Pendiente hematología',
        'Ajuste de indicaciones' => 'Se mantiene tratamiento',
    ])->and($resumen[2]['fields'])->toBe([
        'Cómo se siente' => 'Sin molestias',
        'Observaciones' => 'Alta por mejoría',
    ])->and($resumen[0]['fields'])->toBe(['Motivo' => 'Cefalea']);
});

it('una consulta posterior a la inicial sin estatus cuenta como seguimiento', function (): void {
    $resumen = TelemedicineCaseBitacora::clinicalSummary(collect([
        consultaDePrueba(1, '2026-09-01 08:00', 'CONSULTA INICIAL'),
        consultaDePrueba(2, '2026-09-02 08:00', null),
    ]), CASO_SIN_SEGUIMIENTOS);

    expect(array_column($resumen, 'stage'))->toBe(['Consulta inicial', 'Seguimiento 1']);
});

it('resume los textos largos y avisa que la nota completa está en consultas', function (): void {
    $largo = str_repeat('Paciente refiere dolor abdominal difuso. ', 20);

    $resumen = TelemedicineCaseBitacora::clinicalSummary(collect([
        consultaDePrueba(1, '2026-09-01 08:00', 'CONSULTA INICIAL', [
            'diagnostic_impression' => $largo,
            'reason_consultation' => 'Dolor',
        ]),
    ]), CASO_SIN_SEGUIMIENTOS);

    $impresion = $resumen[0]['fields']['Impresión diagnóstica'];

    expect($resumen[0]['truncated'])->toBeTrue()
        ->and(mb_strlen($impresion))->toBeLessThanOrEqual(TelemedicineCaseBitacora::SUMMARY_TEXT_LIMIT + 1)
        ->and($impresion)->toEndWith('…')
        ->and($resumen[0]['fields']['Motivo'])->toBe('Dolor');
});

it('compacta los signos vitales y omite los campos vacíos', function (): void {
    $resumen = TelemedicineCaseBitacora::clinicalSummary(collect([
        consultaDePrueba(1, '2026-09-01 08:00', 'CONSULTA INICIAL', [
            'reason_consultation' => "Tos\n\n  seca",
            'diagnostic_impression' => '   ',
            'patient_evolution' => '—',
            'pa' => '120/80',
            'fc' => '80',
            'fr' => '',
            'temp' => '37.2',
            'saturacion' => null,
        ]),
    ]), CASO_SIN_SEGUIMIENTOS);

    expect($resumen[0]['fields'])->toBe([
        'Motivo' => 'Tos seca',
        'Signos vitales' => 'PA 120/80 · FC 80 · Temp 37.2',
    ])->and($resumen[0]['truncated'])->toBeFalse();
});

it('sin consultas no hay etapas', function (): void {
    expect(TelemedicineCaseBitacora::clinicalSummary(collect(), CASO_SIN_SEGUIMIENTOS))->toBe([]);
});

it('la bitácora de un caso real trae una etapa por consulta', function (): void {
    $caso = TelemedicineCase::query()
        ->whereHas('consultations', fn ($query) => $query->where('status', 'ALTA MEDICA'))
        ->withCount('consultations')
        ->latest('id')
        ->first();

    if ($caso === null) {
        $this->markTestSkipped('No hay casos con alta médica.');
    }

    $resumen = TelemedicineCaseBitacora::dossier($caso)['clinical_summary'];

    expect($resumen)->toHaveCount($caso->consultations_count)
        ->and($resumen[0]['stage'])->toBe('Consulta inicial')
        ->and(array_column($resumen, 'stage'))->toContain('Alta médica');
});

it('el resumen va justo después de los datos del paciente, en pantalla y en PDF', function (): void {
    $pantalla = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/pages/bitacora-de-caso.blade.php');
    $pdf = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/bitacora-caso.blade.php');

    $pacientePantalla = strpos($pantalla, "'map' => \$dossier['patient']");
    $resumenPantalla = strpos($pantalla, 'bitacora-caso-clinical-summary');
    $alta = strpos($pantalla, "'title' => 'Alta médica'");

    $pacientePdf = strpos($pdf, "'title' => 'Paciente'");
    $resumenPdf = strpos($pdf, 'bitacora-caso-clinical-summary');
    $consultasPdf = strpos($pdf, "'title' => 'Consultas y notas médicas'");

    expect($resumenPantalla)->toBeGreaterThan($pacientePantalla)
        ->and($resumenPantalla)->toBeLessThan($alta)
        ->and($resumenPdf)->toBeGreaterThan($pacientePdf)
        ->and($resumenPdf)->toBeLessThan($consultasPdf);
});

it('la pantalla y el PDF muestran etapas, médico y aviso de texto resumido', function (): void {
    $entradas = TelemedicineCaseBitacora::clinicalSummary(collect([
        consultaDePrueba(1, '2026-09-01 08:00', 'CONSULTA INICIAL', ['reason_consultation' => str_repeat('x ', 200)]),
        consultaDePrueba(2, '2026-09-03 08:00', 'ALTA MEDICA', ['patient_evolution' => 'Resuelto']),
    ]), CASO_SIN_SEGUIMIENTOS);

    $pdf = view('documents.partials.bitacora-caso-clinical-summary', ['entries' => $entradas])->render();
    $pantalla = view('filament.operations.partials.bitacora-caso-clinical-summary', ['entries' => $entradas])->render();

    expect($pantalla)->toContain('Resumen clínico del caso')
        ->toContain('Alta médica')
        ->toContain('Texto resumido');

    expect($pdf)->toContain('Resumen clínico del caso')
        ->toContain('Consulta inicial')
        ->toContain('Alta médica')
        ->toContain('DRA. PRUEBA')
        ->toContain('Texto resumido')
        ->toContain('Resuelto');
});
