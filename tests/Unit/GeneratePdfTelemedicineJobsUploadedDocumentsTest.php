<?php

declare(strict_types=1);

it('GeneratePdfLaboratorio guarda metadata en uploaded_documents con tipo por defecto 11', function (): void {
    $path = dirname(__DIR__, 2).'/app/Jobs/GeneratePdfLaboratorio.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('syncConsultationUploadedDocuments')
        ->toContain('$defaultDocumentTypeId = 11;')
        ->toContain("'ORDEN PARA LABORATORIOS'")
        ->toContain("'document_type_ids' => [\$defaultDocumentTypeId]")
        ->toContain("'uploaded_documents' => array_values(array_merge(\$existingDocuments, [\$newDocument]))");
});

it('GeneratePdfImagenologia guarda metadata en uploaded_documents con tipo por defecto 12', function (): void {
    $path = dirname(__DIR__, 2).'/app/Jobs/GeneratePdfImagenologia.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('syncConsultationUploadedDocuments')
        ->toContain('$defaultDocumentTypeId = 12;')
        ->toContain("'ORDEN PARA ESTUDIOS Y/O IMAGENOLOGIA'")
        ->toContain("'document_type_ids' => [\$defaultDocumentTypeId]")
        ->toContain("'uploaded_documents' => array_values(array_merge(\$existingDocuments, [\$newDocument]))");
});

it('GeneratePdfEspecialista guarda metadata en uploaded_documents con tipo por defecto 13', function (): void {
    $path = dirname(__DIR__, 2).'/app/Jobs/GeneratePdfEspecialista.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('syncConsultationUploadedDocuments')
        ->toContain('$defaultDocumentTypeId = 13;')
        ->toContain("'ORDEN CONSULTA CON ESPECIALISTA'")
        ->toContain("'document_type_ids' => [\$defaultDocumentTypeId]")
        ->toContain("'uploaded_documents' => array_values(array_merge(\$existingDocuments, [\$newDocument]))");
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
        ->toContain('syncConsultationUploadedDocuments')
        ->toContain('$defaultDocumentTypeId = 9;')
        ->toContain("'INFORME MEDICO CONSULTA INICIAL (LARGO)'")
        ->toContain("'document_type_ids' => [\$defaultDocumentTypeId]")
        ->toContain("'uploaded_documents' => array_values(array_merge(\$existingDocuments, [\$newDocument]))");
});
