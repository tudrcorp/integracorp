<?php

declare(strict_types=1);

use App\Http\Controllers\TravelAgencyExportCsvController;
use App\Models\TravelAgency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

// Este test escribe una TravelAgency real para leer el .xlsx generado byte a byte: se
// envuelve en una transacción revertida para no ensuciar la base de desarrollo (ver CLAUDE.md §0.2).
beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

it('genera un xlsx con el numero de cuenta bancario integro, sin notacion cientifica ni perdida de digitos', function (): void {
    $travelAgency = TravelAgency::create([
        'name' => 'AGENCIA DE VIAJE DE PRUEBA XLSX',
        'email' => 'agencia-viaje-test-xlsx-'.uniqid().'@example.test',
        'status' => 'ACTIVO',
        'local_beneficiary_account_number' => '01340353843533025405',
        'extra_beneficiary_account_number' => '000123456789012345',
    ]);

    $token = TravelAgencyExportCsvController::storeIdsAndGetToken([$travelAgency->id]);
    $request = Request::create('/business/export-travel-agencies-csv', 'GET', ['token' => $token]);

    $response = (new TravelAgencyExportCsvController)($request);

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
