<?php

declare(strict_types=1);

it('la tabla de montos de master y general tiene un solo «Comprar plan» en el encabezado, sin acciones masivas', function (string $panel, string $panelId): void {
    $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/IndividualQuotes/RelationManagers/DetailsQuoteRelationManager.php";
    $source = file_get_contents($path);

    expect($source)
        ->toContain("->headerActions([\n                Action::make('buy_plan')")
        ->toContain("->label('Comprar plan')")
        ->toContain('IndividualQuotePurchase::start($quote, '."'{$panelId}')")
        ->toContain(strtoupper($panelId).': falla al comprar plan desde la tabla de montos de la cotización individual')
        ->not->toContain('BulkAction')
        ->not->toContain('dd($th)')
        ->not->toContain('filament.agents.resources.affiliations.create');
})->with([['Master', 'master'], ['General', 'general']]);

it('preselecciona cobertura y protege defaultItems en formularios master y general', function (string $panel): void {
    $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Affiliations/Schemas/AffiliationForm.php";
    $source = file_get_contents($path);

    expect($source)
        ->toContain("request()->query('coverage_id')")
        ->toContain("\$quoteRecord['coverage_id'] ?? null")
        ->toContain('return max(0, $persons - 1);')
        ->not->toContain('return session()->get(\'persons\') - 1;');
})->with(['Master', 'General']);
