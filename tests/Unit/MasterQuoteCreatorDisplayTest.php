<?php

declare(strict_types=1);

use App\Support\Filament\Master\MasterQuoteCreatorDisplay;

it('resuelve el prefijo de agencia con fallback master cuando no hay codigo', function (): void {
    expect(MasterQuoteCreatorDisplay::agencyTypePrefix(null))->toBe('MASTER - ')
        ->and(MasterQuoteCreatorDisplay::agencyTypePrefix(''))->toBe('MASTER - ');
});

it('resuelve nombres de agente y subagente sin id', function (): void {
    expect(MasterQuoteCreatorDisplay::agentName(null))->toBe('—')
        ->and(MasterQuoteCreatorDisplay::subAgentName(null))->toBe('—')
        ->and(MasterQuoteCreatorDisplay::isSubAgent(null))->toBeFalse();
});

it('incluye columnas de creador en tablas master de cotizaciones', function (): void {
    $individualPath = __DIR__.'/../../app/Filament/Master/Resources/IndividualQuotes/Tables/IndividualQuotesTable.php';
    $corporatePath = __DIR__.'/../../app/Filament/Master/Resources/CorporateQuotes/Tables/CorporateQuotesTable.php';

    $individual = file_get_contents($individualPath);
    $corporate = file_get_contents($corporatePath);

    expect($individual)->not->toBeFalse()
        ->toContain('MasterQuoteCreatorDisplay')
        ->toContain("->label('Agencia')")
        ->toContain("->label('Agente')")
        ->toContain("->label('Sub Agente')")
        ->toContain('modifyQueryUsing');

    expect($corporate)->not->toBeFalse()
        ->toContain('MasterQuoteCreatorDisplay')
        ->toContain("->label('Agencia')")
        ->toContain("->label('Agente')")
        ->toContain("->label('Sub Agente')")
        ->toContain('modifyQueryUsing');
});
