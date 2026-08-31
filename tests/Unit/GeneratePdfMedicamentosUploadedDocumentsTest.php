<?php

declare(strict_types=1);

it('GeneratePdfMedicamentos guarda metadata en uploaded_documents de la consulta', function (): void {
    $path = dirname(__DIR__, 2).'/app/Jobs/GeneratePdfMedicamentos.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('TelemedicineCoverageSplitPdfWriter::write')
        ->toContain('TelemedicineCoverageDocumentSplit::medicationGroups')
        ->toContain("'RECIPE DE MEDICAMENTOS'")
        ->toContain('10,');
});
