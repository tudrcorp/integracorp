<?php

declare(strict_types=1);

it('OperationServiceOrderInfolist aplica estilos visuales tipo AgentForm master', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Schemas/OperationServiceOrderInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('private const TABS_CONTAINER')
        ->toContain('private const SECTION_CARD')
        ->toContain("Tabs::make('operationServiceOrderInfolistTabs')")
        ->toContain('->extraAttributes([')
        ->toContain("'class' => self::TABS_CONTAINER")
        ->toContain("'class' => self::SECTION_CARD");
});
