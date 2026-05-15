<?php

declare(strict_types=1);

it('relation managers de consulta exponen tablas con UX consistente', function (): void {
    $base = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineConsultationPatients/RelationManagers';

    $labs = file_get_contents($base.'/TelemedicinePatientLabsRelationManager.php');
    $medications = file_get_contents($base.'/TelemedicinePatientMedicationsRelationManager.php');
    $specialists = file_get_contents($base.'/TelemedicinePatientSpecialistsRelationManager.php');
    $studies = file_get_contents($base.'/TelemedicinePatientStudiesRelationManager.php');

    foreach ([$labs, $medications, $specialists, $studies] as $contents) {
        expect($contents)
            ->toContain('->striped()')
            ->toContain('->defaultSort(\'created_at\', \'desc\')')
            ->toContain('->paginationPageOptions([10, 25, 50])')
            ->toContain('->emptyStateHeading(')
            ->toContain('->emptyStateDescription(')
            ->toContain('Heroicon::');
    }

    expect($labs)
        ->toContain('protected static ?string $recordTitleAttribute = \'laboratory\';')
        ->toContain('->label(\'Cobertura\')');

    expect($medications)
        ->toContain('TelemedicineMedicationCoverage::')
        ->toContain('->with(\'operationInventory\')');

    expect($specialists)
        ->toContain('protected static ?string $recordTitleAttribute = \'specialty\';');

    expect($studies)
        ->toContain('protected static ?string $recordTitleAttribute = \'study\';');
});
