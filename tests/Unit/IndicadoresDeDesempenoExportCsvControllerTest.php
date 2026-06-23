<?php

declare(strict_types=1);

use App\Http\Controllers\IndicadoresDeDesempenoExportCsvController;
use App\Support\IndicadoresDeDesempeno\IndicadoresDeDesempenoCsvRows;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

it('guarda el periodo seleccionado en cache para exportacion csv de indicadores de desempeno', function (): void {
    $token = IndicadoresDeDesempenoExportCsvController::storePeriodAndGetToken('2025-01-01', '2025-06-06');

    expect($token)->toBeString()->not->toBeEmpty();

    $cachedPeriod = Cache::pull('indicadores_de_desempeno_export_csv_'.$token);

    expect($cachedPeriod)->toBe([
        'from' => '2025-01-01',
        'to' => '2025-06-06',
    ]);
});

it('rechaza la descarga csv de indicadores cuando el token no existe o expiro', function (): void {
    $controller = new IndicadoresDeDesempenoExportCsvController;

    $request = Request::create('/operations/export-indicadores-de-desempeno-csv', 'GET', [
        'token' => 'token-inexistente',
    ]);

    expect(fn (): mixed => $controller($request))
        ->toThrow(HttpException::class, 'Token de exportación no válido o expirado.');
});

it('tiene registrada la ruta nombrada de exportacion csv para indicadores de desempeno', function (): void {
    expect(route('operations.indicadores-de-desempeno.export-csv', ['token' => 'x']))->toBeString();
});

it('organiza el csv de indicadores con resumen y secciones detalladas', function (): void {
    $rows = IndicadoresDeDesempenoCsvRows::build('2099-01-01', '2099-01-31');

    expect($rows[0])->toBe(['Indicadores de desempeño'])
        ->and($rows[1])->toBe(['Período desde', '2099-01-01'])
        ->and($rows[2])->toBe(['Período hasta', '2099-01-31'])
        ->and($rows[5])->toBe(['Resumen por colaborador'])
        ->and($rows[6][0])->toBe('Colaborador')
        ->and($rows[6][10])->toBe('Total actividades');

    $sectionTitles = collect($rows)
        ->filter(fn (array $row): bool => count($row) === 1)
        ->pluck(0)
        ->all();

    expect($sectionTitles)->toContain('Detalle: tickets creados')
        ->toContain('Detalle: observaciones')
        ->toContain('Detalle: actualizaciones en sistema')
        ->toContain('Detalle: nuevos proveedores')
        ->toContain('Detalle: cartas de aceptación');
});

it('expone la accion de exportar csv en la pagina de indicadores de desempeno', function (): void {
    $pagePath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/IndicadoresDeDesempeno/Pages/ListIndicadoresDeDesempeno.php';

    expect(file_get_contents($pagePath))->toContain("Action::make('exportCsv')")
        ->toContain('IndicadoresDeDesempenoExportCsvController::storePeriodAndGetToken')
        ->toContain("->route('operations.indicadores-de-desempeno.export-csv'");
});
