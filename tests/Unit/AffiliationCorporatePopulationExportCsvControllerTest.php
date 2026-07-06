<?php

declare(strict_types=1);

use App\Http\Controllers\AffiliationCorporatePopulationExportCsvController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

it('guarda los ids seleccionados en cache para exportacion de poblacion de afiliaciones corporativas', function (): void {
    $token = AffiliationCorporatePopulationExportCsvController::storeIdsAndGetToken(['3', 8, '15']);

    expect($token)->toBeString()->not->toBeEmpty();

    $cachedIds = Cache::pull('affiliation_corporate_population_export_csv_'.$token);

    expect($cachedIds)->toBe([3, 8, 15]);
});

it('incluye columnas de afiliacion corporativa y afiliado en el export csv de poblacion', function (): void {
    $source = file_get_contents(base_path('app/Http/Controllers/AffiliationCorporatePopulationExportCsvController.php'));

    expect($source)
        ->toContain('Cliente corporativo')
        ->toContain('Afiliado #')
        ->toContain('Plan')
        ->toContain('Cobertura')
        ->toContain('Estatus afiliado')
        ->toContain("->where('affiliation_corporate_id', \$record->id)")
        ->toContain("->orderBy('last_name')")
        ->toContain("->orderBy('first_name')");
});

it('rechaza la descarga csv de poblacion de afiliaciones corporativas cuando el token no existe o expiro', function (): void {
    $controller = new AffiliationCorporatePopulationExportCsvController;

    $request = Request::create('/business/export-affiliation-corporates-population-csv', 'GET', [
        'token' => 'token-inexistente',
    ]);

    expect(fn (): mixed => $controller($request))
        ->toThrow(HttpException::class, 'Token de exportación no válido o expirado.');
});

it('tiene registrada la ruta nombrada de exportacion csv con poblacion de afiliaciones corporativas', function (): void {
    expect(route('business.affiliation-corporates.export-population-csv', ['token' => 'x']))->toBeString();
});

it('expone exportacion csv con poblacion en la tabla de afiliaciones corporativas', function (): void {
    $contents = file_get_contents(base_path('app/Filament/Business/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php'));

    expect($contents)
        ->toContain("->label('Exportar CSV con población')")
        ->toContain('exportPopulationCsv')
        ->toContain("'business.affiliation-corporates.export-population-csv'");
});
