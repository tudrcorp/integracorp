<?php

declare(strict_types=1);

use App\Http\Controllers\AgencyExportCsvController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

it('guarda los ids seleccionados en cache para exportacion de agencias', function (): void {
    $token = AgencyExportCsvController::storeIdsAndGetToken(['3', 8, '15']);

    expect($token)->toBeString()->not->toBeEmpty();

    $cachedIds = Cache::pull('agency_export_csv_'.$token);

    expect($cachedIds)->toBe([3, 8, 15]);
});

it('rechaza la descarga csv de agencias cuando el token no existe o expiro', function (): void {
    $controller = new AgencyExportCsvController;

    $request = Request::create('/administration/export-agencies-csv', 'GET', [
        'token' => 'token-inexistente',
    ]);

    expect(fn (): mixed => $controller($request))
        ->toThrow(HttpException::class, 'Token de exportación no válido o expirado.');
});

it('registra la ruta de exportacion csv de agencias en administration', function (): void {
    expect(route('administration.agencies.export-csv', ['token' => 'x']))->toBeString();
});
