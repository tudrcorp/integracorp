<?php

declare(strict_types=1);

it('aplica mejoras de ux en relation manager de detalle de cotizacion individual', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/IndividualQuotes/RelationManagers/DetailsQuoteRelationManager.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("->heading('Detalles de la cotización')")
        ->toContain('->emptyStateHeading(')
        ->toContain('->striped()')
        ->toContain("->defaultSort('subtotal_anual', 'desc')")
        ->toContain('->defaultPaginationPageOption(10)')
        ->toContain("->defaultGroup('plan.description')")
        ->toContain("SelectFilter::make('plan_id')")
        ->toContain("SelectFilter::make('status')")
        ->toContain('->description(fn ($record): string => (int) $record->total_persons')
        ->not->toContain("TextColumn::make('total_persons')")
        ->toContain('Log::error(')
        ->not->toContain('dd($th)');
});
