<?php

declare(strict_types=1);

use App\Http\Controllers\AgencyExportCsvController;
use App\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

// Estos tres tests escriben una Agency real para leer el .xlsx generado byte a byte:
// se envuelven en una transacción revertida para no ensuciar la base de desarrollo (ver CLAUDE.md §0.2).
beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

it('genera un xlsx con el numero de cuenta bancario integro, sin notacion cientifica ni perdida de digitos', function (): void {
    $agency = Agency::create([
        'code' => 'TEST-XLSX-AGY',
        'name_corporative' => 'AGENCIA DE PRUEBA XLSX',
        'email' => 'agencia-test-xlsx-'.uniqid().'@example.test',
        'status' => 'ACTIVO',
        'agency_type_id' => 1,
        'local_beneficiary_account_number' => '01340353843533025405',
        'extra_beneficiary_account_number' => '000123456789012345',
    ]);

    $token = AgencyExportCsvController::storeIdsAndGetToken([$agency->id]);
    $request = Request::create('/administration/export-agencies-csv', 'GET', ['token' => $token]);

    $response = (new AgencyExportCsvController)($request);

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

it('guarda los ids seleccionados en cache para exportacion de agencias', function (): void {
    $token = AgencyExportCsvController::storeIdsAndGetToken(['7', 12, '20']);

    expect($token)->toBeString()->not->toBeEmpty();

    $cachedIds = Cache::pull('agency_export_csv_'.$token);

    expect($cachedIds)->toBe([7, 12, 20]);
});

it('usa un nombre de archivo con prefijo agencias para la descarga xlsx', function (): void {
    $source = file_get_contents(base_path('app/Http/Controllers/AgencyExportCsvController.php'));

    expect($source)->toContain("agencias_'.now()->format('Y-m-d_His').'.xlsx");
});

it('genera un .xlsx real en vez de csv porque el numero de cuenta no cabe legible en una celda numerica', function (): void {
    $source = file_get_contents(base_path('app/Http/Controllers/AgencyExportCsvController.php'));

    expect($source)
        ->toContain('OpenSpout\Writer\XLSX\Writer')
        ->toContain('CommercialStructureBankingExportColumns')
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
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
        ->toContain("->label('Exportar XLS')")
        ->toContain('exportCsvController')
        ->toContain("'business.agencies.export-csv'");
});
