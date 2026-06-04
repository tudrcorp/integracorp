<?php

declare(strict_types=1);

dataset('quote form schemas', [
    'agents individual' => 'app/Filament/Agents/Resources/IndividualQuotes/Schemas/IndividualQuoteForm.php',
    'agents corporate' => 'app/Filament/Agents/Resources/CorporateQuotes/Schemas/CorporateQuoteForm.php',
    'general individual' => 'app/Filament/General/Resources/IndividualQuotes/Schemas/IndividualQuoteForm.php',
    'general corporate' => 'app/Filament/General/Resources/CorporateQuotes/Schemas/CorporateQuoteForm.php',
    'master individual' => 'app/Filament/Master/Resources/IndividualQuotes/Schemas/IndividualQuoteForm.php',
    'master corporate' => 'app/Filament/Master/Resources/CorporateQuotes/Schemas/CorporateQuoteForm.php',
    'business individual' => 'app/Filament/Business/Resources/IndividualQuotes/Schemas/IndividualQuoteForm.php',
    'business corporate' => 'app/Filament/Business/Resources/CorporateQuotes/Schemas/CorporateQuoteForm.php',
]);

it('requiere al menos un rango de edad en los formularios de cotización', function (string $relativePath): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);

    expect($source)
        ->toContain('requireQuoteDetailsRule')
        ->toContain('Debe seleccionar al menos un (1) rango de edad para crear la cotización.')
        ->toContain('->rules(self::requireQuoteDetailsRule())');
})->with('quote form schemas');

it('requiere al menos una persona en los formularios de cotización', function (string $relativePath): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);

    expect($source)
        ->toContain('La cantidad de personas debe ser como mínimo una (1) persona.')
        ->toContain('->minValue(1)');
})->with('quote form schemas');
