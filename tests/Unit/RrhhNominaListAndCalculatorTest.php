<?php

declare(strict_types=1);

use App\Support\Rrhh\RrhhNominaCalculator;
use App\Support\Rrhh\RrhhNominaPeriodo;

it('convierte montos usd a ves con la tasa bcv', function (): void {
    $calculator = new RrhhNominaCalculator;

    expect($calculator->toVes(100, 36.5))->toBe(3650.0)
        ->and($calculator->toVes(10.55, 40))->toBe(422.0);
});

it('configura la tabla de nominas con totales usd y ves', function (): void {
    $table = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhNominas/Tables/RrhhNominasTable.php'
    );

    expect($table)
        ->toContain("TextColumn::make('total_salarios')")
        ->toContain("TextColumn::make('total_descuentos')")
        ->toContain("TextColumn::make('total_asignaciones')")
        ->toContain("TextColumn::make('total_prestamos')")
        ->toContain("TextColumn::make('tasa_bcv')")
        ->toContain('USD$ ')
        ->toContain('VES ')
        ->toContain('periodoLabel');
});

it('expone header action para calcular nomina con periodo quincenal y tasa bcv', function (): void {
    $list = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhNominas/Pages/ListRrhhNominas.php'
    );

    expect($list)
        ->toContain("Action::make('calcularNomina')")
        ->toContain("Select::make('anio')")
        ->toContain("Select::make('periodo')")
        ->toContain('RrhhNominaPeriodo::optionsForYear')
        ->toContain("TextInput::make('tasa_bcv')")
        ->toContain("Action::make('cargarTasaBcv')")
        ->toContain('ApiBcvController::getTasaBcv()')
        ->toContain('RrhhNominaCalculator')
        ->toContain('Calcular nómina')
        ->not->toContain("DatePicker::make('fecha_desde')");
});

it('usa sueldo al 50 por ciento y periodo resuelto en el calculador', function (): void {
    $calculator = file_get_contents(dirname(__DIR__, 2).'/app/Support/Rrhh/RrhhNominaCalculator.php');

    expect($calculator)
        ->toContain('RrhhNominaPeriodo::resolve')
        ->toContain('RrhhNominaPeriodo::sueldoDelPeriodo')
        ->toContain("'anio'")
        ->toContain("'periodo'")
        ->toContain('Ya existe un cálculo para el período');
});

it('agrega columnas de periodo tasa y totales ves via migracion', function (): void {
    $migration = file_get_contents(
        dirname(__DIR__, 2).'/database/migrations/2026_07_23_100950_add_payroll_totals_and_period_to_rrhh_nominas_table.php'
    );

    expect($migration)
        ->toContain("hasColumn('rrhh_nominas', 'fecha_desde')")
        ->toContain("hasColumn('rrhh_nominas', 'fecha_hasta')")
        ->toContain("hasColumn('rrhh_nominas', 'tasa_bcv')")
        ->toContain("hasColumn('rrhh_nominas', 'total_prestamos')")
        ->toContain("hasColumn('rrhh_nominas', 'total_salarios_ves')")
        ->toContain("hasColumn('rrhh_nominas', 'total_neto_ves')");
});

it('agrega anio y numero de periodo via migracion', function (): void {
    $migration = file_get_contents(
        dirname(__DIR__, 2).'/database/migrations/2026_07_23_125607_add_periodo_and_anio_to_rrhh_nominas_table.php'
    );

    expect($migration)
        ->toContain("hasColumn('rrhh_nominas', 'anio')")
        ->toContain("hasColumn('rrhh_nominas', 'periodo')");
});

it('actualiza el modelo de nomina con fillable de totales y periodo', function (): void {
    $model = file_get_contents(dirname(__DIR__, 2).'/app/Models/RrhhNomina.php');

    expect($model)
        ->toContain("'anio'")
        ->toContain("'periodo'")
        ->toContain("'fecha_desde'")
        ->toContain("'fecha_hasta'")
        ->toContain("'tasa_bcv'")
        ->toContain("'total_prestamos'")
        ->toContain("'total_salarios_ves'")
        ->toContain('function periodoLabel()')
        ->toContain('function detalleNomina(): HasMany')
        ->toContain('P%02d');
});

it('deshabilita create manual en el resource de nominas', function (): void {
    $resource = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhNominas/RrhhNominaResource.php'
    );

    expect($resource)
        ->toContain('public static function canCreate(): bool')
        ->toContain('return false;')
        ->not->toContain('CreateRrhhNomina::route');
});

it('expone helper de sueldo del periodo', function (): void {
    expect(RrhhNominaPeriodo::sueldoDelPeriodo(600))->toBe(300.0);
});
