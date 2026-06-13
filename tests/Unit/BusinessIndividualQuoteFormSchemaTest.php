<?php

declare(strict_types=1);

it('usa pestañas con contenedor estilizado en el formulario de cotización individual', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/IndividualQuotes/Schemas/IndividualQuoteForm.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("Tabs::make('individualQuoteFormTabs')")
        ->toContain('Tab::make(')
        ->toContain('private const TABS_CONTAINER')
        ->toContain("'class' => self::TABS_CONTAINER")
        ->not->toContain('Wizard::make');
});

it('requiere al menos un rango de edad seleccionado para crear la cotización', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/IndividualQuotes/Schemas/IndividualQuoteForm.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain('requireQuoteDetailsRule')
        ->toContain('Debe seleccionar al menos un (1) rango de edad para crear la cotización.')
        ->toContain('->rules(self::requireQuoteDetailsRule())');
});

it('requiere al menos una persona en la cotización', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/IndividualQuotes/Schemas/IndividualQuoteForm.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain('La cantidad de personas debe ser como mínimo una (1) persona.')
        ->toContain('->minValue(1)');
});
