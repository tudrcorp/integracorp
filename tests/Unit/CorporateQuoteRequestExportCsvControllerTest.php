<?php

declare(strict_types=1);

use App\Http\Controllers\CorporateQuoteRequestExportCsvController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

it('guarda los ids seleccionados en cache para exportacion de solicitudes corporativas', function (): void {
    $token = CorporateQuoteRequestExportCsvController::storeIdsAndGetToken(['7', 12, '20']);

    expect($token)->toBeString()->not->toBeEmpty();

    $cachedIds = Cache::pull('corporate_quote_request_export_csv_'.$token);

    expect($cachedIds)->toBe([7, 12, 20]);
});

it('rechaza la descarga csv de solicitudes corporativas cuando el token no existe o expiro', function (): void {
    $controller = new CorporateQuoteRequestExportCsvController;

    $request = Request::create('/business/export-corporate-quote-requests-csv', 'GET', [
        'token' => 'token-inexistente',
    ]);

    expect(fn (): mixed => $controller($request))
        ->toThrow(HttpException::class, 'Token de exportación no válido o expirado.');
});

it('tiene registrada la ruta nombrada de exportacion csv para solicitudes corporativas', function (): void {
    expect(route('business.corporate-quote-requests.export-csv', ['token' => 'x']))->toBeString();
});
