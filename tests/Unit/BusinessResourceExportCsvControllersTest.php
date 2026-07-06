<?php

declare(strict_types=1);

use App\Http\Controllers\BusinessAppointmentExportCsvController;
use App\Http\Controllers\CityExportCsvController;
use App\Http\Controllers\CorporateQuoteExportCsvController;
use App\Http\Controllers\IndividualQuoteExportCsvController;
use App\Http\Controllers\RenovationExportCsvController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

it('guarda los ids seleccionados en cache para exportacion de ciudades', function (): void {
    $token = CityExportCsvController::storeIdsAndGetToken(['7', 12, '20']);

    expect($token)->toBeString()->not->toBeEmpty();

    $cachedIds = Cache::pull('city_export_csv_'.$token);

    expect($cachedIds)->toBe([7, 12, 20]);
});

it('rechaza la descarga csv de ciudades cuando el token no existe o expiro', function (): void {
    $controller = new CityExportCsvController;

    $request = Request::create('/business/export-cities-csv', 'GET', [
        'token' => 'token-inexistente',
    ]);

    expect(fn (): mixed => $controller($request))
        ->toThrow(HttpException::class, 'Token de exportación no válido o expirado.');
});

it('guarda los ids seleccionados en cache para exportacion de citas', function (): void {
    $token = BusinessAppointmentExportCsvController::storeIdsAndGetToken(['7', 12, '20']);

    expect($token)->toBeString()->not->toBeEmpty();

    $cachedIds = Cache::pull('business_appointment_export_csv_'.$token);

    expect($cachedIds)->toBe([7, 12, 20]);
});

it('rechaza la descarga csv de citas cuando el token no existe o expiro', function (): void {
    $controller = new BusinessAppointmentExportCsvController;

    $request = Request::create('/business/export-business-appointments-csv', 'GET', [
        'token' => 'token-inexistente',
    ]);

    expect(fn (): mixed => $controller($request))
        ->toThrow(HttpException::class, 'Token de exportación no válido o expirado.');
});

it('guarda los ids seleccionados en cache para exportacion de cotizaciones corporativas', function (): void {
    $token = CorporateQuoteExportCsvController::storeIdsAndGetToken(['7', 12, '20']);

    expect($token)->toBeString()->not->toBeEmpty();

    $cachedIds = Cache::pull('corporate_quote_export_csv_'.$token);

    expect($cachedIds)->toBe([7, 12, 20]);
});

it('rechaza la descarga csv de cotizaciones corporativas cuando el token no existe o expiro', function (): void {
    $controller = new CorporateQuoteExportCsvController;

    $request = Request::create('/business/export-corporate-quotes-csv', 'GET', [
        'token' => 'token-inexistente',
    ]);

    expect(fn (): mixed => $controller($request))
        ->toThrow(HttpException::class, 'Token de exportación no válido o expirado.');
});

it('guarda los ids seleccionados en cache para exportacion de cotizaciones individuales', function (): void {
    $token = IndividualQuoteExportCsvController::storeIdsAndGetToken(['7', 12, '20']);

    expect($token)->toBeString()->not->toBeEmpty();

    $cachedIds = Cache::pull('individual_quote_export_csv_'.$token);

    expect($cachedIds)->toBe([7, 12, 20]);
});

it('rechaza la descarga csv de cotizaciones individuales cuando el token no existe o expiro', function (): void {
    $controller = new IndividualQuoteExportCsvController;

    $request = Request::create('/business/export-individual-quotes-csv', 'GET', [
        'token' => 'token-inexistente',
    ]);

    expect(fn (): mixed => $controller($request))
        ->toThrow(HttpException::class, 'Token de exportación no válido o expirado.');
});

it('guarda los ids seleccionados en cache para exportacion de renovaciones', function (): void {
    $token = RenovationExportCsvController::storeIdsAndGetToken(['7', 12, '20']);

    expect($token)->toBeString()->not->toBeEmpty();

    $cachedIds = Cache::pull('renovation_export_csv_'.$token);

    expect($cachedIds)->toBe([7, 12, 20]);
});

it('rechaza la descarga csv de renovaciones cuando el token no existe o expiro', function (): void {
    $controller = new RenovationExportCsvController;

    $request = Request::create('/business/export-renovations-csv', 'GET', [
        'token' => 'token-inexistente',
    ]);

    expect(fn (): mixed => $controller($request))
        ->toThrow(HttpException::class, 'Token de exportación no válido o expirado.');
});

it('tiene registradas las rutas nombradas de exportacion csv del panel business', function (): void {
    expect(route('business.cities.export-csv', ['token' => 'x']))->toBeString();
    expect(route('business.business-appointments.export-csv', ['token' => 'x']))->toBeString();
    expect(route('business.corporate-quotes.export-csv', ['token' => 'x']))->toBeString();
    expect(route('business.individual-quotes.export-csv', ['token' => 'x']))->toBeString();
    expect(route('business.renovations.export-csv', ['token' => 'x']))->toBeString();
});

it('expone exportacion csv en las tablas del panel business', function (): void {
    $tableFiles = [
        'app/Filament/Business/Resources/BusinessAppointments/Tables/BusinessAppointmentsTable.php',
        'app/Filament/Business/Resources/CorporateQuotes/Tables/CorporateQuotesTable.php',
        'app/Filament/Business/Resources/IndividualQuotes/Tables/IndividualQuotesTable.php',
    ];

    foreach ($tableFiles as $file) {
        $contents = file_get_contents(base_path($file));

        expect($contents)
            ->toContain("->label('Exportar CSV')")
            ->toContain('exportCsvController');
    }

    expect(file_get_contents(base_path('app/Filament/Business/Resources/BusinessAppointments/Tables/BusinessAppointmentsTable.php')))
        ->toContain("'business.business-appointments.export-csv'");

    expect(file_get_contents(base_path('app/Filament/Business/Resources/Renovations/Tables/RenovationsTable.php')))
        ->toContain("'business.renovations.export-csv'");

    expect(file_get_contents(base_path('app/Filament/Shared/Renovations/RenovationsTable.php')))
        ->toContain("->label('Exportar CSV')")
        ->toContain('exportCsvController');
});
