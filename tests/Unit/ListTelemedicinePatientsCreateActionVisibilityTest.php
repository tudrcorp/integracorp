<?php

declare(strict_types=1);

it('crear paciente solo es visible para usuarios TDG sin supplier_id', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Pages/ListTelemedicinePatients.php');

    expect($contents)
        ->toContain('CreateAction::make()')
        ->toContain('OperationsSupplierScope::currentSupplierId() === null')
        ->not->toContain("in_array('ATENMEDI'");
});
