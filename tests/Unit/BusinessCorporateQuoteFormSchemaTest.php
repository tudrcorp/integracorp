<?php

declare(strict_types=1);

it('usa pestañas con contenedor estilizado en el formulario de cotización corporativa', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuotes/Schemas/CorporateQuoteForm.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("Tabs::make('corporateQuoteFormTabs')")
        ->toContain('Tab::make(')
        ->toContain('private const TABS_CONTAINER')
        ->toContain("'class' => self::TABS_CONTAINER")
        ->not->toContain('Wizard::make')
        ->not->toContain('Step::make(');
});

it('requiere al menos un rango de edad seleccionado para crear la cotización', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuotes/Schemas/CorporateQuoteForm.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain('requireQuoteDetailsRule')
        ->toContain('Debe seleccionar al menos un (1) rango de edad para crear la cotización.')
        ->toContain('->rules(self::requireQuoteDetailsRule())');
});

it('requiere al menos una persona en la cotización', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuotes/Schemas/CorporateQuoteForm.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain('La cantidad de personas debe ser como mínimo una (1) persona.')
        ->toContain('->minValue(1)');
});

it('filtra planes activos por tipo basico o dress tylor', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuotes/Schemas/CorporateQuoteForm.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("Checkbox::make('quote_type_basico')")
        ->toContain("Checkbox::make('quote_type_dress_tylor')")
        ->toContain('planOptionsForQuoteType')
        ->toContain("->where('status', 'ACTIVO')")
        ->toContain("normalizeQuotePlanType(\$get('type'))")
        ->not->toContain("->hidden(fn (Get \$get) => \$get('type') == 'DRESS-TAILOR')");
});

it('usa repetidor dinámico de rangos de edad desacoplado de ids fijos', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuotes/Schemas/CorporateQuoteForm.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain('emptyQuoteDetailRows')
        ->toContain('syncQuoteDetailsForPlan')
        ->toContain("Repeater::make('details_quote')")
        ->toContain("Repeater::make('details_quote_multiple')")
        ->not->toContain("Repeater::make('details_quote_plan_inicial')")
        ->not->toContain("Repeater::make('details_quote_plan_ideal')")
        ->not->toContain("Repeater::make('details_quote_plan_especial')");
});
