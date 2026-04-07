<?php

declare(strict_types=1);

it('configura OperationTypeServicesTable con UX de catálogo', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationTypeServices/Tables/OperationTypeServicesTable.php';
    $contents = file_get_contents($path);
    expect($contents)->toContain('->heading(\'Tipos de servicio\'')
        ->toContain('->defaultSort(\'description\'')
        ->toContain("TextColumn::make('description')")
        ->toContain('lineClamp(2)')
        ->toContain('heroicon-o-wrench-screwdriver')
        ->toContain('SelectFilter::make(\'status\'')
        ->toContain('OperationTypeService::query()')
        ->toContain('ViewAction::make()->label(\'Ver\'');
});

it('configura OperationTypeNegotiationsTable con UX de catálogo', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationTypeNegotiations/Tables/OperationTypeNegotiationsTable.php';
    $contents = file_get_contents($path);
    expect($contents)->toContain('->heading(\'Tipos de negociación\'')
        ->toContain('->defaultSort(\'description\'')
        ->toContain("TextColumn::make('description')")
        ->toContain('lineClamp(2)')
        ->toContain('heroicon-o-scale')
        ->toContain('SelectFilter::make(\'status\'')
        ->toContain('OperationTypeNegotiation::query()')
        ->toContain('ViewAction::make()->label(\'Ver\'');
});
