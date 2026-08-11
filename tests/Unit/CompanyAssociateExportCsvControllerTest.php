<?php

declare(strict_types=1);

use App\Http\Controllers\CompanyAssociateExportCsvController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

it('guarda los ids seleccionados en cache para exportacion de asociados', function (): void {
    $token = CompanyAssociateExportCsvController::storeIdsAndGetToken(['7', 12, '20']);

    expect($token)->toBeString()->not->toBeEmpty();

    $cachedIds = Cache::pull('company_associate_export_csv_'.$token);

    expect($cachedIds)->toBe([7, 12, 20]);
});

it('rechaza la descarga csv de asociados cuando el token no existe o expiro', function (): void {
    $controller = new CompanyAssociateExportCsvController;

    $request = Request::create('/business/export-company-associates-csv', 'GET', [
        'token' => 'token-inexistente',
    ]);

    expect(fn (): mixed => $controller($request))
        ->toThrow(HttpException::class, 'Token de exportación no válido o expirado.');
});

it('expone la bulk action de exportar csv en la tabla de asociados', function (): void {
    $actions = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CompanyAssociates/Actions/CompanyAssociatesTableActions.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CompanyAssociates/Tables/CompanyAssociatesTable.php');
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

    expect($actions)
        ->toContain('exportCsvBulkAction')
        ->toContain("BulkAction::make('exportCsv')")
        ->toContain('CompanyAssociateExportCsvController::storeIdsAndGetToken')
        ->toContain('business.company-associates.export-csv');

    expect($table)
        ->toContain('CompanyAssociatesTableActions::exportCsvBulkAction');

    expect($routes)
        ->toContain("->name('business.company-associates.export-csv')")
        ->toContain('CompanyAssociateExportCsvController::class');
});
