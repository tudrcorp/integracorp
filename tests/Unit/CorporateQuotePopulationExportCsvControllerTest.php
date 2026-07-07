<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

it('ya no expone exportacion csv con poblacion en cotizaciones corporativas', function (): void {
    $table = file_get_contents(base_path('app/Filament/Business/Resources/CorporateQuotes/Tables/CorporateQuotesTable.php'));
    $routes = file_get_contents(base_path('routes/web.php'));

    expect($table)
        ->not->toContain("->label('Exportar CSV con población')")
        ->not->toContain('exportPopulationCsv')
        ->not->toContain('CorporateQuotePopulationExportCsvController');

    expect($routes)
        ->not->toContain('CorporateQuotePopulationExportCsvController')
        ->not->toContain('export-corporate-quotes-population-csv');
});
