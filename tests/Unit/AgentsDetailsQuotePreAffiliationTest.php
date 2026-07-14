<?php

declare(strict_types=1);

it('implementa preafiliacion en relation manager de cotizacion individual del panel agentes', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Agents/Resources/IndividualQuotes/RelationManagers/DetailsQuoteRelationManager.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("BulkAction::make('quote_multiple')")
        ->toContain("->label('Pre Afiliación')")
        ->toContain("->modalHeading('PREAFILIACIÓN')")
        ->toContain('Solo se puede procesar una cotización a la vez para generar la afiliación.')
        ->toContain("session()->put('data_records', \$records->toArray())")
        ->toContain("session()->put('persons', max(1, (int) (\$record->total_persons ?? 1)))")
        ->toContain("\$individualQuote->status = 'APROBADA'")
        ->toContain('filament.agents.resources.affiliations.create')
        ->toContain("'id' => \$livewire->ownerRecord->id")
        ->toContain("'coverage_id' => \$record->coverage_id")
        ->toContain('Log::error(')
        ->toContain('AGENTS: Falla al generar preafiliación desde detalle de cotización individual')
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
