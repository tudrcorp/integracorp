<?php

declare(strict_types=1);

it('GeneratePdfMedicamentos guarda metadata en uploaded_documents de la consulta', function (): void {
    $path = dirname(__DIR__, 2).'/app/Jobs/GeneratePdfMedicamentos.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('syncConsultationUploadedDocuments')
        ->toContain('TelemedicineConsultationPatient::query()->find($consultationId)')
        ->toContain('OperationDocumentList::query()')
        ->toContain('whereKey($defaultDocumentTypeId)')
        ->toContain("'RECIPE DE MEDICAMENTOS'")
        ->toContain("'document_type_ids' => [\$defaultDocumentTypeId]")
        ->toContain("'uploaded_documents' => array_values(array_merge(\$existingDocuments, [\$newDocument]))");
});
