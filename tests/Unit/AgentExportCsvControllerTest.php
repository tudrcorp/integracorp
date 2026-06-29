<?php

declare(strict_types=1);

use App\Http\Controllers\AgentExportCsvController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

it('guarda los ids seleccionados en cache para exportacion de agentes', function (): void {
    $token = AgentExportCsvController::storeIdsAndGetToken(['5', 11, '18']);

    expect($token)->toBeString()->not->toBeEmpty();

    $cachedIds = Cache::pull('agent_export_csv_'.$token);

    expect($cachedIds)->toBe([5, 11, 18]);
});

it('rechaza la descarga csv de agentes cuando el token no existe o expiro', function (): void {
    $controller = new AgentExportCsvController;

    $request = Request::create('/administration/export-agents-csv', 'GET', [
        'token' => 'token-inexistente',
    ]);

    expect(fn (): mixed => $controller($request))
        ->toThrow(HttpException::class, 'Token de exportación no válido o expirado.');
});

it('registra la ruta de exportacion csv de agentes en administration', function (): void {
    expect(route('administration.agents.export-csv', ['token' => 'x']))->toBeString();
});
