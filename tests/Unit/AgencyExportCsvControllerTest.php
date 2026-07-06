<?php

declare(strict_types=1);

use App\Http\Controllers\AgencyExportCsvController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

it('guarda los ids seleccionados en cache para exportacion de agencias', function (): void {
    $token = AgencyExportCsvController::storeIdsAndGetToken(['7', 12, '20']);

    expect($token)->toBeString()->not->toBeEmpty();

    $cachedIds = Cache::pull('agency_export_csv_'.$token);

    expect($cachedIds)->toBe([7, 12, 20]);
});

it('usa un nombre de archivo con prefijo agencias para la descarga csv', function (): void {
    $source = file_get_contents(base_path('app/Http/Controllers/AgencyExportCsvController.php'));

    expect($source)->toContain("agencias_'.now()->format('Y-m-d_His').'.csv");
});

it('rechaza la descarga csv de agencias cuando el token no existe o expiro', function (): void {
    $controller = new AgencyExportCsvController;

    $request = Request::create('/business/export-agencies-csv', 'GET', [
        'token' => 'token-inexistente',
    ]);

    expect(fn (): mixed => $controller($request))
        ->toThrow(HttpException::class, 'Token de exportación no válido o expirado.');
});

it('tiene registrada la ruta nombrada de exportacion csv de agencias en business', function (): void {
    expect(route('business.agencies.export-csv', ['token' => 'x']))->toBeString();
});

it('registra la ruta de exportacion csv de agencias en administration', function (): void {
    expect(route('administration.agencies.export-csv', ['token' => 'x']))->toBeString();
});

it('expone exportacion csv en la tabla de agencias', function (): void {
    $contents = file_get_contents(base_path('app/Filament/Business/Resources/Agencies/Tables/AgenciesTable.php'));

    expect($contents)
        ->toContain("->label('Exportar CSV')")
        ->toContain('exportCsvController')
        ->toContain("'business.agencies.export-csv'");
});
