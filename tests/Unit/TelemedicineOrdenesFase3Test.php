<?php

declare(strict_types=1);

use App\Support\Telemedicine\TelemedicineConsultationStageLabel;

$basePath = dirname(__DIR__, 2);

it('etiqueta la consulta inicial y trata cualquier otro estado como seguimiento', function (): void {
    expect(TelemedicineConsultationStageLabel::forStatus('CONSULTA INICIAL'))->toBe('Consulta Inicial')
        ->and(TelemedicineConsultationStageLabel::forStatus('consulta inicial'))->toBe('Consulta Inicial')
        ->and(TelemedicineConsultationStageLabel::forStatus('EN SEGUIMIENTO'))->toBe('Seguimiento')
        ->and(TelemedicineConsultationStageLabel::forStatus('ALTA MEDICA'))->toBe('Seguimiento')
        ->and(TelemedicineConsultationStageLabel::forStatus(null))->toBe('')
        ->and(TelemedicineConsultationStageLabel::forStatus('   '))->toBe('');
});

it('lee la etapa del payload del documento', function (): void {
    expect(TelemedicineConsultationStageLabel::forDocumentData(['consultation_status' => 'EN SEGUIMIENTO']))
        ->toBe('Seguimiento')
        ->and(TelemedicineConsultationStageLabel::forDocumentData([]))->toBe('');
});

it('la plantilla de ordenes usa la etapa de la consulta y ya no la cobertura en el encabezado', function () use ($basePath): void {
    $orden = file_get_contents($basePath.'/resources/views/documents/partials/telemedicine-orden-homologada.blade.php');

    expect($orden)
        ->toContain('TelemedicineConsultationStageLabel::forDocumentData')
        ->toContain('{{ $stageLabel }}')
        ->not->toContain('$coverageGroup')
        ->not->toContain('Tipo de servicio')
        ->toContain("'imagenologia' => 'Imagenología'")
        ->toContain("'especialista' => 'Especialistas'")
        ->toContain("default => 'Laboratorios'")
        ->not->toContain('Orden de laboratorios')
        ->not->toContain('Orden de estudios / imagenología')
        ->not->toContain('Referencia a especialistas');
});

it('las tres ordenes llevan el estado de la consulta al pdf desde create y desde la regeneracion', function () use ($basePath): void {
    $create = file_get_contents($basePath.'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php');
    $regeneration = file_get_contents($basePath.'/app/Support/Telemedicine/TelemedicineCaseDocumentRegenerationService.php');

    expect(substr_count($create, "'consultation_status' => \$record['status'] ?? null,"))->toBe(3)
        ->and(substr_count($regeneration, "'consultation_status' => \$consultation->status,"))->toBe(3);
});

it('el recipe de medicamentos conserva su encabezado con la cobertura', function () use ($basePath): void {
    $recipe = file_get_contents($basePath.'/resources/views/documents/partials/telemedicine-recipe-homologado.blade.php');

    expect($recipe)
        ->toContain('$coverageGroup')
        ->toContain('{{ $coverageGroup }}');
});
