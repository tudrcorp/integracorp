<?php

declare(strict_types=1);

use App\Support\Telemedicine\TelemedicineAmdFollowUpVisit;
use App\Support\Telemedicine\TelemedicineCaseTdgReassignmentCoordination;
use App\Support\Telemedicine\TelemedicineConsultationReview;
use App\Support\Telemedicine\TelemedicineFollowUpReportDocument;
use App\Support\Telemedicine\TelemedicineInformePdfRenderer;
use App\Support\Telemedicine\TelemedicineInitialDiagnosisUpdater;

uses(Tests\TestCase::class);

/*
 * Seguimiento hecho como visita AMD: el formulario oculta el diagnóstico
 * principal, la historia clínica de seguimiento y las preguntas; la revisión, el
 * informe y la sincronización del diagnóstico no pueden usar lo que quedó
 * escrito y oculto. No escribe en la base.
 */

function amdFollowUpServiceId(): int
{
    return TelemedicineCaseTdgReassignmentCoordination::AMD_SERVICE_LIST_ID;
}

function amdFollowUpFormSource(): string
{
    return (string) file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php'
    );
}

/**
 * @return array<string, mixed>
 */
function amdFollowUpReportData(array $extra = []): array
{
    return [
        'fecha' => '19/10/2026',
        'code_reference' => 'REF-AMD',
        'name_patient' => 'PACIENTE DE PRUEBA',
        'ci_patient' => '12345678',
        'age_patient' => '40',
        'diagnostic_impression' => 'NEUMONÍA ADQUIRIDA EN LA COMUNIDAD',
        'doctor_name' => 'MÉDICO DE PRUEBA',
        'code_mpps' => '123456',
        'physical_exam' => null,
        ...$extra,
    ];
}

/*
 * ---------------------------------------------------------------------------
 * La regla
 * ---------------------------------------------------------------------------
 */

it('aplica solo a un servicio AMD sin alta médica', function (mixed $service, mixed $alta, bool $aplica): void {
    expect(TelemedicineAmdFollowUpVisit::applies($service, $alta))->toBe($aplica);
})->with([
    'AMD' => [amdFollowUpServiceId(), false, true],
    'AMD como texto del select' => [(string) amdFollowUpServiceId(), null, true],
    'AMD con alta médica' => [amdFollowUpServiceId(), true, false],
    'AMD con alta médica como texto' => [amdFollowUpServiceId(), '1', false],
    'otro servicio' => [amdFollowUpServiceId() + 1, false, false],
    'sin servicio' => [null, false, false],
    'valor no numérico' => ['AMD', false, false],
]);

it('en la visita AMD el diagnóstico a sincronizar sale solo de lo guardado', function (): void {
    $estadoConTextoOculto = [
        'telemedicine_service_list_id' => amdFollowUpServiceId(),
        'feedbackOne' => false,
        TelemedicineInitialDiagnosisUpdater::FORM_FIELD => 'TEXTO ESCRITO ANTES DE ELEGIR AMD',
    ];

    expect(TelemedicineAmdFollowUpVisit::diagnosisToSync(null, $estadoConTextoOculto))->toBe('')
        ->and(TelemedicineAmdFollowUpVisit::diagnosisToSync('DIAGNÓSTICO GUARDADO', $estadoConTextoOculto))->toBe('DIAGNÓSTICO GUARDADO');
});

it('fuera de la AMD se conserva el respaldo histórico del formulario', function (mixed $service, mixed $alta): void {
    $estado = [
        'telemedicine_service_list_id' => $service,
        'feedbackOne' => $alta,
        TelemedicineInitialDiagnosisUpdater::FORM_FIELD => 'DIAGNÓSTICO DEL FORMULARIO',
    ];

    expect(TelemedicineAmdFollowUpVisit::diagnosisToSync(null, $estado))->toBe('DIAGNÓSTICO DEL FORMULARIO')
        ->and(TelemedicineAmdFollowUpVisit::diagnosisToSync('GUARDADO', $estado))->toBe('GUARDADO');
})->with([
    'otro servicio' => [amdFollowUpServiceId() + 1, false],
    'AMD con alta médica' => [amdFollowUpServiceId(), true],
]);

/*
 * ---------------------------------------------------------------------------
 * Formulario
 * ---------------------------------------------------------------------------
 */

it('oculta las tres secciones del cuestionario en la visita AMD', function (string $seccion): void {
    expect(amdFollowUpFormSource())->toMatch(
        '/Fieldset::make\(\''.preg_quote($seccion, '/').'\'\)\s*->hidden\(fn \(Get \$get\): bool => self::isAmdFollowUpVisit\(\$get\)\)/'
    );
})->with([
    'Diagnóstico principal',
    'Historia clínica de seguimiento',
    'Preguntas de Seguimiento',
]);

it('el aviso AMD ocupa el lugar de las secciones ocultas', function (): void {
    $source = amdFollowUpFormSource();

    expect($source)->toContain('->visible(fn (Get $get): bool => self::isAmdFollowUpVisit($get));')
        ->and(strpos($source, 'self::amdFollowUpNotice(),'))->toBeLessThan(strpos($source, "Fieldset::make('Diagnóstico principal')"))
        // La decisión mira el servicio y el alta, igual que la regla compartida.
        ->and($source)->toContain("\$get('telemedicine_service_list_id'),\n            \$get('feedbackOne'),");

    $html = view('filament.telemedicina.consultations.amd-follow-up-notice', ['notice' => TelemedicineAmdFollowUpVisit::NOTICE])->render();

    expect($html)->toContain('Seguimiento por visita AMD')
        ->toContain('«Informar AMD»')
        ->toContain('«Examen físico AMD»')
        ->toContain('Encontrará los botones AMD más abajo, junto al tipo de servicio.')
        ->toContain('role="note"');
});

it('crear y editar sincronizan el diagnóstico con la regla AMD, sin leer el campo oculto', function (string $page): void {
    $source = (string) file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/'.$page.'.php'
    );

    expect($source)->toContain('TelemedicineAmdFollowUpVisit::diagnosisToSync(')
        ->not->toContain('?? $this->data[TelemedicineInitialDiagnosisUpdater::FORM_FIELD]');
})->with([
    'crear' => ['CreateTelemedicineConsultationPatient'],
    'editar' => ['EditTelemedicineConsultationPatient'],
]);

/*
 * ---------------------------------------------------------------------------
 * Revisión
 * ---------------------------------------------------------------------------
 */

it('la revisión de la visita AMD no muestra lo que quedó escrito y oculto', function (): void {
    $estado = [
        'status' => 'EN SEGUIMIENTO',
        'telemedicine_service_list_id' => amdFollowUpServiceId(),
        'feedbackOne' => false,
        'current_illness_history' => 'TEXTO OCULTO',
        'patient_evolution' => 'EVOLUCIÓN OCULTA',
        'cuestion_1' => 'RESPUESTA OCULTA',
        'observations' => 'CONTROL EN 48 HORAS',
    ];

    $seguimiento = TelemedicineConsultationReview::build($estado)['sections'][1];

    expect($seguimiento['fields'])->not->toHaveKey('Historia de la enfermedad actual')
        ->not->toHaveKey('Evolución')
        ->not->toHaveKey('Cómo se siente')
        ->toHaveKey('Observaciones');
});

it('la revisión de un seguimiento que no es AMD sigue mostrando el cuestionario', function (mixed $service, bool $alta): void {
    $seguimiento = TelemedicineConsultationReview::build([
        'status' => 'EN SEGUIMIENTO',
        'telemedicine_service_list_id' => $service,
        'feedbackOne' => $alta,
        'current_illness_history' => 'HISTORIA',
        'patient_evolution' => 'EVOLUCIÓN',
        'cuestion_1' => 'BIEN',
    ])['sections'][1];

    expect($seguimiento['fields'])->toHaveKey('Historia de la enfermedad actual')
        ->toHaveKey('Evolución')
        ->toHaveKey('Cómo se siente');
})->with([
    'otro servicio' => [amdFollowUpServiceId() + 1, false],
    'AMD con alta médica' => [amdFollowUpServiceId(), true],
]);

/*
 * ---------------------------------------------------------------------------
 * Informe de Seguimiento
 * ---------------------------------------------------------------------------
 */

it('el informe de la visita AMD toma historia y evolución de lo guardado, no del formulario', function (): void {
    $registro = [
        'id' => 10,
        'telemedicine_case_id' => 5,
        'telemedicine_patient_id' => 7,
        'telemedicine_service_list_id' => amdFollowUpServiceId(),
        'current_illness_history' => null,
        'patient_evolution' => null,
    ];
    $formulario = [
        'telemedicine_service_list_id' => amdFollowUpServiceId(),
        'feedbackOne' => false,
        'diagnostic_impression' => 'NEUMONÍA',
        'current_illness_history' => 'TEXTO OCULTO',
        'patient_evolution' => 'EVOLUCIÓN OCULTA',
    ];

    $payload = TelemedicineFollowUpReportDocument::payloadFromCreateData($registro, $formulario, [], 'PACIENTE');

    expect($payload['current_illness_history'])->toBeNull()
        ->and($payload['patient_evolution'])->toBeNull()
        ->and($payload['is_amd_visit'])->toBeTrue()
        ->and($payload['diagnostic_impression'])->toBe('NEUMONÍA');
});

it('el informe de un seguimiento que no es AMD conserva lo escrito en el formulario', function (): void {
    $payload = TelemedicineFollowUpReportDocument::payloadFromCreateData(
        ['id' => 10, 'telemedicine_case_id' => 5, 'telemedicine_service_list_id' => amdFollowUpServiceId() + 1],
        ['diagnostic_impression' => 'NEUMONÍA', 'current_illness_history' => 'HISTORIA', 'patient_evolution' => 'EVOLUCIÓN'],
        [],
        'PACIENTE',
    );

    expect($payload['current_illness_history'])->toBe('HISTORIA')
        ->and($payload['patient_evolution'])->toBe('EVOLUCIÓN')
        ->and($payload['is_amd_visit'])->toBeFalse();
});

it('el informe de la visita AMD omite historia y evolución vacías y conserva el diagnóstico', function (): void {
    $html = view(TelemedicineInformePdfRenderer::VIEW_SEGUIMIENTO, ['data' => amdFollowUpReportData(['is_amd_visit' => true])])->render();

    expect($html)->toContain('Diagnóstico principal de la consulta inicial')
        ->toContain('NEUMONÍA ADQUIRIDA EN LA COMUNIDAD')
        ->not->toContain('Historia de la enfermedad actual')
        ->not->toContain('Evolución del paciente');
});

it('un seguimiento AMD antiguo con historia la conserva al regenerarse', function (): void {
    $html = view(TelemedicineInformePdfRenderer::VIEW_SEGUIMIENTO, ['data' => amdFollowUpReportData([
        'is_amd_visit' => true,
        'current_illness_history' => 'HISTORIA REGISTRADA ANTES DEL CAMBIO',
    ])])->render();

    expect($html)->toContain('Historia de la enfermedad actual')
        ->toContain('HISTORIA REGISTRADA ANTES DEL CAMBIO')
        ->not->toContain('Evolución del paciente');
});

it('un seguimiento que no es AMD sigue mostrando las secciones aunque estén vacías', function (): void {
    $html = view(TelemedicineInformePdfRenderer::VIEW_SEGUIMIENTO, ['data' => amdFollowUpReportData(['is_amd_visit' => false])])->render();

    expect($html)->toContain('Historia de la enfermedad actual')
        ->toContain('Evolución del paciente');
});
