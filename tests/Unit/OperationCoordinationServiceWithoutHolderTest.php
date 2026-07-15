<?php

declare(strict_types=1);

it('crea coordinación desde telemedicina sin exigir rol de guardia ni campos de titular', function (): void {
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/OperationCoordinationServiceController.php');

    expect($controller)
        ->not->toContain('OperationOnCallUser')
        ->not->toContain('date_OnCall')
        ->not->toContain("'holder'")
        ->not->toContain("'ci_holder'")
        ->toContain("'patient'")
        ->toContain("'ci_patient'");
});

it('elimina holder y ci_holder del modelo, UI y migración de coordinación', function (): void {
    $root = dirname(__DIR__, 2);

    expect(file_get_contents($root.'/app/Models/OperationCoordinationService.php'))
        ->not->toContain("'holder'")
        ->not->toContain("'ci_holder'");

    expect(file_get_contents($root.'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php'))
        ->not->toContain("TextColumn::make('holder')")
        ->not->toContain("TextColumn::make('ci_holder')");

    expect(file_get_contents($root.'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceForm.php'))
        ->not->toContain("TextInput::make('holder')")
        ->not->toContain("TextInput::make('ci_holder')");

    expect(file_get_contents($root.'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceInfolist.php'))
        ->not->toContain("TextEntry::make('holder')")
        ->not->toContain("TextEntry::make('ci_holder')");

    $migration = collect(glob($root.'/database/migrations/*drop_holder_and_ci_holder_from_operation_coordination_services_table.php'))
        ->first();

    expect($migration)->not->toBeNull();
    expect(file_get_contents($migration))
        ->toContain("'holder'")
        ->toContain("'ci_holder'")
        ->toContain('dropColumn');
});
