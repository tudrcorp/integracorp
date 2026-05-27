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
