<?php

declare(strict_types=1);

use App\Support\Telemedicine\TelemedicineCaseDocumentRegenerationService;
use App\Support\Telemedicine\TelemedicineInformeLargoDataBuilder;

$basePath = dirname(__DIR__, 2);

it('nombra el archivo del informe medico con el sufijo informe-medico', function (): void {
    $name = TelemedicineInformeLargoDataBuilder::pdfDocumentName([
        'ci_patient' => 'V-12345678',
        'code_reference' => 'REF-100',
    ]);

    expect($name)->toBe('V-12345678-REF-100-informe-medico.pdf')
        ->and(TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_MEDICO)->toBe('informe-medico');
});

it('el generador y el registrador AMD usan el sufijo canonico del informe medico', function () use ($basePath): void {
    $generator = file_get_contents($basePath.'/app/Support/Telemedicine/TelemedicineInformeLargoPdfGenerator.php');
    $builder = file_get_contents($basePath.'/app/Support/Telemedicine/TelemedicineInformeLargoDataBuilder.php');
    $amd = file_get_contents($basePath.'/app/Support/Telemedicine/TelemedicineAmdInformRegistrar.php');

    expect($generator)
        ->toContain('TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_MEDICO')
        ->not->toContain("'informe-largo'");

    expect($builder)
        ->toContain('TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_MEDICO')
        ->not->toContain("'informe-largo'");

    expect($amd)->not->toContain("'informe-largo'");
});

it('el informe corto ya no se emite al guardar la consulta', function () use ($basePath): void {
    $create = file_get_contents($basePath.'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php');

    expect($create)
        ->not->toContain('GeneratePdfInformeMedicoCorto')
        ->not->toContain('dataInformeCorteo')
        ->toContain('GeneratePdfInformeMedicoLargo');
});

it('el informe corto se conserva para los documentos historicos pero sale del selector de regeneracion', function () use ($basePath): void {
    /**
     * 462 consultas tienen el informe corto registrado en `uploaded_documents`:
     * el job y la plantilla siguen en el repositorio para que esos documentos
     * se puedan listar y descargar.
     */
    $service = file_get_contents($basePath.'/app/Support/Telemedicine/TelemedicineCaseDocumentRegenerationService.php');

    expect(is_file($basePath.'/app/Jobs/GeneratePdfInformeMedicoCorto.php'))->toBeTrue()
        ->and(is_file($basePath.'/resources/views/documents/informe-medico-corto.blade.php'))->toBeTrue();

    expect($service)
        ->toContain("DOCUMENT_INFORME_CORTO = 'informe-corto'")
        ->toContain('@deprecated')
        ->not->toContain('GeneratePdfInformeMedicoCorto')
        ->not->toContain('DOCUMENT_INFORME_LARGO');
});

it('renombra el tipo 9 a INFORME MEDICO en el catalogo y en los historicos', function () use ($basePath): void {
    $migration = file_get_contents($basePath.'/database/migrations/2026_09_21_140000_rename_informe_medico_document_type.php');
    $job = file_get_contents($basePath.'/app/Jobs/GeneratePdfInformeMedicoLargo.php');
    $amd = file_get_contents($basePath.'/app/Support/Telemedicine/TelemedicineAmdInformRegistrar.php');

    expect($migration)
        ->toContain("OLD_NAME = 'INFORME MEDICO CONSULTA INICIAL (LARGO)'")
        ->toContain("NEW_NAME = 'INFORME MEDICO'")
        ->toContain('uploaded_documents');

    expect($job)
        ->toContain('$defaultDocumentTypeName = \'INFORME MEDICO\';')
        ->not->toContain('INFORME MEDICO CONSULTA INICIAL (LARGO)');

    expect($amd)->not->toContain('INFORME MEDICO CONSULTA INICIAL (LARGO)');
});
