<?php

declare(strict_types=1);

it('la tabla de montos del panel agentes tiene un solo «Comprar plan» en el encabezado, sin acciones masivas', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Agents/Resources/IndividualQuotes/RelationManagers/DetailsQuoteRelationManager.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("->headerActions([\n                Action::make('buy_plan')")
        ->toContain("->label('Comprar plan')")
        ->toContain("->modalHeading('COMPRAR PLAN')")
        ->toContain('IndividualQuotePurchase::start($quote, '."'agents')")
        ->toContain('IndividualQuotePurchase::canPurchase($this->getOwnerRecord())')
        ->toContain('Log::error(')
        ->not->toContain('BulkAction')
        ->not->toContain('toolbarActions')
        ->not->toContain('dd($th)')
        ->not->toContain('filament.business.resources.affiliations.create');
});

it('protege defaultItems del repeater de afiliados contra conteos negativos', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Agents/Resources/Affiliations/Schemas/AffiliationForm.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain('return max(0, $persons - 1);')
        ->toContain("session()->get('persons')")
        ->toContain("session()->get('data_records')")
        ->not->toContain('return session()->get(\'persons\') - 1;');
});

it('preselecciona cobertura desde query o data_records en el formulario de afiliacion agentes', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Agents/Resources/Affiliations/Schemas/AffiliationForm.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("Select::make('coverage_id')")
        ->toContain("request()->query('coverage_id')")
        ->toContain("\$quoteRecord['coverage_id'] ?? null")
        ->toContain('->dehydrated()');
});
