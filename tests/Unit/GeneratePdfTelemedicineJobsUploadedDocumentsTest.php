<?php

declare(strict_types=1);

it('el writer de cobertura genera el PDF y reemplaza la familia de documentos', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineCoverageSplitPdfWriter.php'
    );

    expect($contents)
        ->toContain('Pdf::loadView($view, [\'data\' => $payload])')
        ->toContain("setPaper('a4', \$orientation)")
        ->toContain('TelemedicineConsultationUploadedDocuments::replaceFamily');
});

it('GeneratePdfLaboratorio guarda metadata en uploaded_documents con tipo por defecto 11', function (): void {
    $path = dirname(__DIR__, 2).'/app/Jobs/GeneratePdfLaboratorio.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('TelemedicineCoverageSplitPdfWriter::write')
        ->toContain('TelemedicineCoverageDocumentSplit::orderGroups')
        ->toContain("'ORDEN PARA LABORATORIOS'")
        ->toContain('11,');
});

it('GeneratePdfImagenologia guarda metadata en uploaded_documents con tipo por defecto 12', function (): void {
    $path = dirname(__DIR__, 2).'/app/Jobs/GeneratePdfImagenologia.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('TelemedicineCoverageSplitPdfWriter::write')
        ->toContain("'ORDEN PARA ESTUDIOS Y/O IMAGENOLOGIA'")
        ->toContain('12,');
});

it('GeneratePdfEspecialista guarda metadata en uploaded_documents con tipo por defecto 13', function (): void {
    $path = dirname(__DIR__, 2).'/app/Jobs/GeneratePdfEspecialista.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('TelemedicineCoverageSplitPdfWriter::write')
        ->toContain("'ORDEN CONSULTA CON ESPECIALISTA'")
        ->toContain('13,');
});

it('GeneratePdfInformeMedicoCorto guarda metadata en uploaded_documents con tipo por defecto 14', function (): void {
    $path = dirname(__DIR__, 2).'/app/Jobs/GeneratePdfInformeMedicoCorto.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('syncConsultationUploadedDocuments')
        ->toContain('$defaultDocumentTypeId = 14;')
        ->toContain("'INFORME MEDICO CONSULTA INICIAL (CORTO)'")
        ->toContain("'document_type_ids' => [\$defaultDocumentTypeId]")
        ->toContain("'uploaded_documents' => array_values(array_merge(\$existingDocuments, [\$newDocument]))");
});

it('GeneratePdfInformeMedicoLargo guarda metadata en uploaded_documents con tipo por defecto 9', function (): void {
    $path = dirname(__DIR__, 2).'/app/Jobs/GeneratePdfInformeMedicoLargo.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('TelemedicineInformeLargoPdfGenerator::generateAndSave')
        ->toContain('syncConsultationUploadedDocuments');
});
