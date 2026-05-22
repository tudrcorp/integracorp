<?php

declare(strict_types=1);

it('usa pestañas con contenedor estilizado en infolist de cotización individual', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/IndividualQuotes/Schemas/IndividualQuoteInfolist.php');

    expect($source)
        ->toContain("Tabs::make('individualQuoteInfolistTabs')")
        ->toContain('persistTab()')
        ->toContain('private const TABS_CONTAINER')
        ->toContain('Tab::make(');
});

it('usa pestañas con contenedor estilizado en infolist de cotización corporativa', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuotes/Schemas/CorporateQuoteInfolist.php');

    expect($source)
        ->toContain("Tabs::make('corporateQuoteInfolistTabs')")
        ->toContain('persistTab()')
        ->toContain('private const TABS_CONTAINER')
        ->toContain('Tab::make(');
});

it('usa pestañas con contenedor estilizado en infolist de solicitud corporativa', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuoteRequests/Schemas/CorporateQuoteRequestInfolist.php');

    expect($source)
        ->toContain("Tabs::make('corporateQuoteRequestInfolistTabs')")
        ->toContain('persistTab()')
        ->toContain('private const TABS_CONTAINER')
        ->toContain('Tab::make(');
});
