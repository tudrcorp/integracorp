<?php

declare(strict_types=1);

it('mejora la ux de la modal de reasignación a tdg en casos de telemedicina', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineCases/Tables/TelemedicineCasesTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("BulkAction::make('reasignar_caso')")
        ->toContain("->modalHeading('Confirmar reasignación de casos')")
        ->toContain('->modalDescription(')
        ->toContain('->modalIcon(Heroicon::OutlinedArrowsRightLeft)')
        ->toContain("->modalSubmitActionLabel('Sí, reasignar a TDG')")
        ->toContain("->modalCancelActionLabel('Cancelar')")
        ->toContain('->deselectRecordsAfterCompletion()')
        ->toContain('->closeModalByClickingAway(false)')
        ->toContain("Textarea::make('reassignment_observation')")
        ->toContain('->required()')
        ->toContain('->minLength(10)')
        ->toContain('ObservationCase::query()->create')
        ->toContain('OperationCoordinationService::query()->create')
        ->toContain("'date_solicitud' => now()")
        ->toContain("'servicie' => \$mainServiceName")
        ->toContain("'specific_service' => \$derivedServiceName")
        ->toContain("'status' => 'PENDIENTE'")
        ->toContain("'managed_by' => 'TDG'")
        ->toContain('$casesCount = $records->count();');
});

it('mejora la ux de la modal de reasignación de doctor en casos de telemedicina', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineCases/Tables/TelemedicineCasesTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("BulkAction::make('reasignar_doctor')")
        ->toContain("->modalHeading('Confirmar reasignación de doctor')")
        ->toContain('->modalDescription(')
        ->toContain('->modalIcon(Heroicon::OutlinedUserPlus)')
        ->toContain("->modalSubmitActionLabel('Sí, reasignar doctor')")
        ->toContain('->searchable()')
        ->toContain('->preload()')
        ->toContain('$doctorName = TelemedicineDoctor::query()')
        ->toContain('$casesCount = $records->count();');
});
