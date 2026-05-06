<?php

declare(strict_types=1);

use App\Http\Controllers\TravelAgencyExportCsvController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

it('guarda los ids seleccionados en cache para exportacion de agencias de viaje', function (): void {
    $token = TravelAgencyExportCsvController::storeIdsAndGetToken(['7', 12, '20']);

    expect($token)->toBeString()->not->toBeEmpty();

    $cachedIds = Cache::pull('travel_agency_export_csv_'.$token);

    expect($cachedIds)->toBe([7, 12, 20]);
});

it('rechaza la descarga csv de agencias de viaje cuando el token no existe o expiro', function (): void {
    $controller = new TravelAgencyExportCsvController;

    $request = Request::create('/business/export-travel-agencies-csv', 'GET', [
        'token' => 'token-inexistente',
    ]);

    expect(fn (): mixed => $controller($request))
        ->toThrow(HttpException::class, 'Token de exportación no válido o expirado.');
});
