<?php

declare(strict_types=1);

use App\Http\Controllers\AgentExportCsvController;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

// Este test escribe un Agent real para leer el .xlsx generado byte a byte: se envuelve en
// una transacción revertida para no ensuciar la base de desarrollo (ver CLAUDE.md §0.2).
beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

it('genera un xlsx con el numero de cuenta bancario integro, sin notacion cientifica ni perdida de digitos', function (): void {
    $agent = Agent::create([
        'name' => 'AGENTE DE PRUEBA XLSX',
        'email' => 'agente-test-xlsx-'.uniqid().'@example.test',
        'status' => 'ACTIVO',
        'agent_type_id' => 1,
        'local_beneficiary_account_number' => '01340353843533025405',
        'extra_beneficiary_account_number' => '000123456789012345',
    ]);

    $token = AgentExportCsvController::storeIdsAndGetToken([$agent->id]);
    $request = Request::create('/administration/export-agents-csv', 'GET', ['token' => $token]);

    $response = (new AgentExportCsvController)($request);

    expect($response)->toBeInstanceOf(BinaryFileResponse::class);

    $path = $response->getFile()->getPathname();

    $reader = new Reader;
    $reader->open($path);

    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
        break;
    }
    $reader->close();
    @unlink($path);

    expect($rows)->toHaveCount(2);

    $headerIndex = array_search('Nat. nº cuenta', $rows[0], true);
    $extraIndex = array_search('Int. nº cuenta', $rows[0], true);

    expect($headerIndex)->not->toBeFalse();
    expect($extraIndex)->not->toBeFalse();
    expect($rows[1][$headerIndex])->toBe('01340353843533025405');
    expect($rows[1][$extraIndex])->toBe('000123456789012345');
});

it('guarda los ids seleccionados en cache para exportacion de agentes', function (): void {
    $token = AgentExportCsvController::storeIdsAndGetToken(['7', 12, '20']);

    expect($token)->toBeString()->not->toBeEmpty();

    $cachedIds = Cache::pull('agent_export_csv_'.$token);

    expect($cachedIds)->toBe([7, 12, 20]);
});

it('usa un nombre de archivo con prefijo agentes para la descarga xlsx', function (): void {
    $source = file_get_contents(base_path('app/Http/Controllers/AgentExportCsvController.php'));

    expect($source)->toContain("agentes_'.now()->format('Y-m-d_His').'.xlsx");
});

it('genera un .xlsx real en vez de csv porque el numero de cuenta no cabe legible en una celda numerica', function (): void {
    $source = file_get_contents(base_path('app/Http/Controllers/AgentExportCsvController.php'));

    expect($source)
        ->toContain('OpenSpout\Writer\XLSX\Writer')
        ->toContain('CommercialStructureBankingExportColumns')
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
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
        ->toContain("->label('Exportar XLS')")
        ->toContain('exportCsvController')
        ->toContain("'business.agents.export-csv'");
});
