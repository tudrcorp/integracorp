<?php

declare(strict_types=1);

it('implementa preafiliacion en relation manager master de cotizacion individual', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Master/Resources/IndividualQuotes/RelationManagers/DetailsQuoteRelationManager.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("BulkAction::make('quote_multiple')")
        ->toContain("->label('Pre Afiliación')")
        ->toContain("session()->put('persons', max(1, (int) (\$record->total_persons ?? 1)))")
        ->toContain("'coverage_id' => \$record->coverage_id")
        ->toContain('filament.master.resources.affiliations.create')
        ->toContain('MASTER: Falla al generar preafiliación desde detalle de cotización individual')
        ->not->toContain('dd($th)')
        ->not->toContain('filament.agents.resources.affiliations.create');
});

it('implementa preafiliacion en relation manager general de cotizacion individual', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/General/Resources/IndividualQuotes/RelationManagers/DetailsQuoteRelationManager.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("BulkAction::make('quote_multiple')")
        ->toContain("->label('Pre Afiliación')")
        ->toContain("session()->put('persons', max(1, (int) (\$record->total_persons ?? 1)))")
        ->toContain("'coverage_id' => \$record->coverage_id")
        ->toContain('filament.general.resources.affiliations.create')
        ->toContain('GENERAL: Falla al generar preafiliación desde detalle de cotización individual')
        ->not->toContain('dd($th)');
});

it('preselecciona cobertura y protege defaultItems en formularios master y general', function (string $panel): void {
    $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Affiliations/Schemas/AffiliationForm.php";
    $source = file_get_contents($path);

    expect($source)
        ->toContain("request()->query('coverage_id')")
        ->toContain("\$quoteRecord['coverage_id'] ?? null")
        ->toContain('return max(0, $persons - 1);')
        ->not->toContain('return session()->get(\'persons\') - 1;');
})->with(['Master', 'General']);
