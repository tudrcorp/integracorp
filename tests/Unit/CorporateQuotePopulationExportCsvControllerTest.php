<?php

declare(strict_types=1);

use App\Http\Controllers\CorporateQuotePopulationExportCsvController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

it('guarda los ids seleccionados en cache para exportacion de poblacion corporativa', function (): void {
    $token = CorporateQuotePopulationExportCsvController::storeIdsAndGetToken(['7', 12, '20']);

    expect($token)->toBeString()->not->toBeEmpty();

    $cachedIds = Cache::pull('corporate_quote_population_export_csv_'.$token);

    expect($cachedIds)->toBe([7, 12, 20]);
});

it('incluye columnas de corporativo y afiliado en el export csv de poblacion', function (): void {
    $source = file_get_contents(base_path('app/Http/Controllers/CorporateQuotePopulationExportCsvController.php'));

    expect($source)
        ->toContain('Código')
        ->toContain('Afiliado #')
        ->toContain('Nombre')
        ->toContain('Apellido')
        ->toContain('Cédula')
        ->toContain("->orderBy('last_name')")
        ->toContain("->orderBy('first_name')");
});

it('rechaza la descarga csv de poblacion cuando el token no existe o expiro', function (): void {
    $controller = new CorporateQuotePopulationExportCsvController;

    $request = Request::create('/business/export-corporate-quotes-population-csv', 'GET', [
        'token' => 'token-inexistente',
    ]);

    expect(fn (): mixed => $controller($request))
        ->toThrow(HttpException::class, 'Token de exportación no válido o expirado.');
});

it('tiene registrada la ruta nombrada de exportacion csv con poblacion', function (): void {
    expect(route('business.corporate-quotes.export-population-csv', ['token' => 'x']))->toBeString();
});

it('expone exportacion csv con poblacion en la tabla de cotizaciones corporativas', function (): void {
    $contents = file_get_contents(base_path('app/Filament/Business/Resources/CorporateQuotes/Tables/CorporateQuotesTable.php'));

    expect($contents)
        ->toContain("->label('Exportar CSV con población')")
        ->toContain('exportPopulationCsv')
        ->toContain("'business.corporate-quotes.export-population-csv'");
});
