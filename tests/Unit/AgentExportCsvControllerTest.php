<?php

declare(strict_types=1);

use App\Http\Controllers\AgentExportCsvController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

it('guarda los ids seleccionados en cache para exportacion de agentes', function (): void {
    $token = AgentExportCsvController::storeIdsAndGetToken(['7', 12, '20']);

    expect($token)->toBeString()->not->toBeEmpty();

    $cachedIds = Cache::pull('agent_export_csv_'.$token);

    expect($cachedIds)->toBe([7, 12, 20]);
});

it('usa un nombre de archivo con prefijo agentes para la descarga csv', function (): void {
    $source = file_get_contents(base_path('app/Http/Controllers/AgentExportCsvController.php'));

    expect($source)->toContain("agentes_'.now()->format('Y-m-d_His').'.csv");
});

it('rechaza la descarga csv de agentes cuando el token no existe o expiro', function (): void {
    $controller = new AgentExportCsvController;

    $request = Request::create('/business/export-agents-csv', 'GET', [
        'token' => 'token-inexistente',
    ]);

    expect(fn (): mixed => $controller($request))
        ->toThrow(HttpException::class, 'Token de exportación no válido o expirado.');
});

it('tiene registrada la ruta nombrada de exportacion csv de agentes en business', function (): void {
    expect(route('business.agents.export-csv', ['token' => 'x']))->toBeString();
});

it('registra la ruta de exportacion csv de agentes en administration', function (): void {
    expect(route('administration.agents.export-csv', ['token' => 'x']))->toBeString();
});

it('expone exportacion csv en la tabla de agentes', function (): void {
    $contents = file_get_contents(base_path('app/Filament/Business/Resources/Agents/Tables/AgentsTable.php'));

    expect($contents)
        ->toContain("->label('Exportar CSV')")
        ->toContain('exportCsvController')
        ->toContain("'business.agents.export-csv'");
});
