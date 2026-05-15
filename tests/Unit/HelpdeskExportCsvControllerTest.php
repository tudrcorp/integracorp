<?php

declare(strict_types=1);

use App\Http\Controllers\HelpdeskExportCsvController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

it('guarda los ids seleccionados en cache para exportacion de tickets helpdesk', function (): void {
    $token = HelpdeskExportCsvController::storeIdsAndGetToken(['7', 12, '20']);

    expect($token)->toBeString()->not->toBeEmpty();

    $cachedIds = Cache::pull('helpdesk_export_csv_'.$token);

    expect($cachedIds)->toBe([7, 12, 20]);
});

it('rechaza la descarga csv de helpdesk cuando el token no existe o expiro', function (): void {
    $controller = new HelpdeskExportCsvController;

    $request = Request::create('/business/export-helpdesks-csv', 'GET', [
        'token' => 'token-inexistente',
    ]);

    expect(fn (): mixed => $controller($request))
        ->toThrow(HttpException::class, 'Token de exportación no válido o expirado.');
});

it('tiene registradas las rutas nombradas de exportacion csv para helpdesk', function (): void {
    expect(route('business.helpdesks.export-csv', ['token' => 'x']))->toBeString();
    expect(route('administration.helpdesks.export-csv', ['token' => 'x']))->toBeString();
    expect(route('operations.helpdesks.export-csv', ['token' => 'x']))->toBeString();
    expect(route('marketing.helpdesks.export-csv', ['token' => 'x']))->toBeString();
});
