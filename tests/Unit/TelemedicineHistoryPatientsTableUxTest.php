<?php

declare(strict_types=1);

it('mejora la UI de la tabla de historias clínicas en operaciones', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineHistoryPatients/Tables/TelemedicineHistoryPatientsTable.php');

    expect($contents)
        ->toContain('ColumnGroup::make(\'Antecedentes patológicos\')')
        ->toContain('ColumnGroup::make(\'Auditoría\')')
        ->toContain('pathology_summary')
        ->toContain('allergies_summary')
        ->toContain('recordUrl(')
        ->toContain('ActionGroup::make')
        ->toContain('TernaryFilter::make(\'with_pathologies\')')
        ->toContain('Filter::make(\'history_date\')')
        ->toContain('deferFilters(false)')
        ->toContain('positivePathologyLabels');
});
