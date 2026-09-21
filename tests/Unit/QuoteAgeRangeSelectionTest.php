<?php

declare(strict_types=1);

use App\Support\Quotes\QuoteAgeRangeSelection;

it('normaliza la categoria de cotizacion', function (): void {
    expect(QuoteAgeRangeSelection::normalizeType('DRESS-TAILOR'))->toBe('DRESS-TAILOR')
        ->and(QuoteAgeRangeSelection::normalizeType('BASICO'))->toBe('BASICO')
        ->and(QuoteAgeRangeSelection::normalizeType(null))->toBe('BASICO');
});

it('no considera cambio de categoria si el tipo es el mismo', function (): void {
    expect(QuoteAgeRangeSelection::categoryChanged('DRESS-TAILOR', 'DRESS-TAILOR'))->toBeFalse()
        ->and(QuoteAgeRangeSelection::categoryChanged('BASICO', 'DRESS-TAILOR'))->toBeTrue()
        ->and(QuoteAgeRangeSelection::categoryChanged(null, 'BASICO'))->toBeFalse();
});

it('resuelve el id del plan seleccionado y ignora cotizacion multiple', function (): void {
    expect(QuoteAgeRangeSelection::selectedPlanId(16))->toBe(16)
        ->and(QuoteAgeRangeSelection::selectedPlanId('16'))->toBe(16)
        ->and(QuoteAgeRangeSelection::selectedPlanId('CM'))->toBeNull()
        ->and(QuoteAgeRangeSelection::selectedPlanId(null))->toBeNull();
});

it('reconstruye las filas de rango solo si el repetidor esta vacio', function (): void {
    expect(QuoteAgeRangeSelection::rowsIfMissing('CM', []))->toBe([])
        ->and(QuoteAgeRangeSelection::rowsIfMissing(null, []))->toBe([])
        ->and(QuoteAgeRangeSelection::rowsIfMissing(16, [
            ['plan_id' => 16, 'age_range_id' => 24, 'total_persons' => 2],
        ]))->toBeNull();
});

it('el formulario corporativo conserva el plan dress tylor al cambiar de pestana', function (): void {
    $fuente = (string) file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuotes/Schemas/CorporateQuoteForm.php',
    );

    expect($fuente)
        ->toContain("Radio::make('type')")
        ->toContain('QuoteAgeRangeSelection::categoryChanged')
        ->toContain('QuoteAgeRangeSelection::rowsIfMissing')
        ->toContain('QuoteAgeRangeSelection::emptyRowsForPlan')
        ->toContain('QuoteAgeRangeSelection::optionsForPlan')
        ->toContain("Hidden::make('quote_details_restore')")
        ->not->toContain("Checkbox::make('quote_type_basico')")
        ->not->toContain("Checkbox::make('quote_type_dress_tylor')");
});
