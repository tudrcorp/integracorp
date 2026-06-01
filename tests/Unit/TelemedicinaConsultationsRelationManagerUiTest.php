<?php

declare(strict_types=1);

it('relation manager de consultas en panel telemedicina replica estilos y lógica de cobertura', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineCases/RelationManagers/ConsultationsRelationManager.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("->heading('Bitácora de gestión médica')")
        ->toContain('->striped()')
        ->toContain('consultationCoverageBadgesHtml')
        ->toContain('TelemedicineCoverageCatalog::')
        ->toContain('coverageStatusBadgeHtml')
        ->toContain('svgIconShieldCheck')
        ->toContain("ColumnGroup::make('Cobertura', [")
        ->toContain('TelemedicineMedicationCoverage::isCovered')
        ->toContain('telemedicine-case-table-ios');
});
