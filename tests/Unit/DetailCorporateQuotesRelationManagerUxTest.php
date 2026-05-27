<?php

declare(strict_types=1);

it('aplica mejoras de ux/ui en relation manager de detalle de cotización corporativa', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuotes/RelationManagers/DetailCoporateQuotesRelationManager.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("->heading('Detalles de la cotización corporativa')")
        ->toContain('->emptyStateHeading(')
        ->toContain('->striped()')
        ->toContain("->defaultSort('subtotal_anual', 'desc')")
        ->toContain('->defaultPaginationPageOption(10)')
        ->toContain("->defaultGroup('plan.description')")
        ->toContain("SelectFilter::make('status')")
        ->toContain('->deferFilters(false)')
        ->toContain('Log::error(')
        ->not->toContain('dd($th)');
});
