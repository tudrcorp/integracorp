<?php

declare(strict_types=1);

use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicinePatient;
use App\Support\Telemedicine\TelemedicineFollowUpReportDocument;
use App\Support\Telemedicine\TelemedicineInformePdfRenderer;

uses(Tests\TestCase::class);

/**
 * La observación que el médico escribe en la consulta sale en el informe de esa
 * consulta: el informe médico en la inicial y el informe de seguimiento en cada
 * seguimiento. Vacía no deja rastro, ni título ni caja.
 */
function telemedicineObservationsInformeData(mixed $observations): array
{
    return [
        'fecha' => '05/10/2026',
        'code_reference' => 'REF-1',
        'name_patient' => 'PACIENTE PRUEBA',
        'ci_patient' => 'V-1',
        'reason' => 'FIEBRE',
        'diagnostic_impression' => 'SINDROME FEBRIL',
        'current_illness_history' => 'PERSISTE',
        'patient_evolution' => 'ESTABLE',
        'medicationsArr' => [],
        'labsArr' => [],
        'studiesArr' => [],
        'doctor_name' => 'DR PRUEBA',
        'code_mpps' => '123',
        'signature' => null,
        'observations' => $observations,
    ];
}

it('el informe médico y el de seguimiento muestran la observación cuando existe', function (string $view): void {
    $html = view($view, ['data' => telemedicineObservationsInformeData("Paciente ansioso.\nControl en 24 horas.")])->render();

    expect($html)
        ->toContain('>Observaciones</div>')
        ->toContain("Paciente ansioso.\nControl en 24 horas.");
})->with([
    'informe médico' => TelemedicineInformePdfRenderer::VIEW_LARGO,
    'informe de seguimiento' => TelemedicineInformePdfRenderer::VIEW_SEGUIMIENTO,
]);

it('sin observación no aparece ni el título ni la caja', function (string $view, mixed $observations): void {
    $html = view($view, ['data' => telemedicineObservationsInformeData($observations)])->render();

    expect($html)->not->toContain('>Observaciones</div>');
})->with([
    'informe médico' => TelemedicineInformePdfRenderer::VIEW_LARGO,
    'informe de seguimiento' => TelemedicineInformePdfRenderer::VIEW_SEGUIMIENTO,
])->with([
    'nula' => [null],
    'vacía' => [''],
    'solo espacios' => ["  \n  "],
]);

it('la observación se escapa: no se interpreta como HTML', function (): void {
    $html = view(TelemedicineInformePdfRenderer::VIEW_SEGUIMIENTO, [
        'data' => telemedicineObservationsInformeData('<b>alerta</b>'),
    ])->render();

    expect($html)->toContain('&lt;b&gt;alerta&lt;/b&gt;')
        ->not->toContain('<b>alerta</b>');
});

it('el informe de seguimiento lleva la observación del seguimiento al crear y al regenerar', function (): void {
    $fromCreate = TelemedicineFollowUpReportDocument::payloadFromCreateData(
        ['telemedicine_case_id' => 1, 'id' => 30, 'observations' => 'GUARDADA'],
        ['diagnostic_impression' => 'SINDROME FEBRIL', 'observations' => 'DEL FORMULARIO'],
        ['full_name' => 'DR B'],
        'PACIENTE PRUEBA',
    );

    $consultation = new TelemedicineConsultationPatient([
        'status' => 'EN SEGUIMIENTO',
        'diagnostic_impression' => 'SINDROME FEBRIL',
        'observations' => 'DE LA CONSULTA',
    ]);
    $consultation->id = 30;
    $doctor = new TelemedicineDoctor(['full_name' => 'DR B']);
    $patient = new TelemedicinePatient(['full_name' => 'PACIENTE PRUEBA']);

    $fromConsultation = TelemedicineFollowUpReportDocument::payloadFromConsultation($consultation, $doctor, $patient);

    expect($fromCreate['observations'])->toBe('DEL FORMULARIO')
        ->and($fromConsultation['observations'])->toBe('DE LA CONSULTA')
        ->and(TelemedicineFollowUpReportDocument::payload([])['observations'])->toBeNull();
});

it('el informe médico lleva la observación al crear la consulta y en el informe AMD', function (): void {
    $base = dirname(__DIR__, 2);
    $create = file_get_contents($base.'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php');
    $amd = file_get_contents($base.'/app/Support/Telemedicine/TelemedicineInformeLargoDataBuilder.php');
    $regeneration = file_get_contents($base.'/app/Support/Telemedicine/TelemedicineCaseDocumentRegenerationService.php');

    expect($create)->toContain("'observations' => \$this->data['observations'] ?? \$record['observations'] ?? null,")
        ->and($amd)->toContain("'observations' => (string) (\$context['observations'] ?? ''),")
        ->and($regeneration)->toContain("'observations' => \$consultation->observations,");
});
