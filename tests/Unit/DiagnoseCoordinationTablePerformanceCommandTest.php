<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * El comando ya se revierte solo; la transacción del test es una red más.
 */
beforeEach(function (): void {
    DB::beginTransaction();
});

afterEach(function (): void {
    DB::rollBack();
});

it('diagnostica el cuadro de control sin escribir nada', function (): void {
    $antes = [
        'sessions' => DB::table('sessions')->count(),
        'jobs' => DB::table('jobs')->count(),
        'logs' => DB::table('logs')->count(),
        'cache' => DB::table('cache')->count(),
    ];

    $this->artisan('operations:diagnose-coordination-table', ['--iterations' => 1])
        ->expectsOutputToContain('== Entorno')
        ->expectsOutputToContain('== Latencia a MySQL')
        ->expectsOutputToContain('== Consultas del cuadro de control')
        ->expectsOutputToContain('No se escribió nada')
        ->assertSuccessful();

    expect([
        'sessions' => DB::table('sessions')->count(),
        'jobs' => DB::table('jobs')->count(),
        'logs' => DB::table('logs')->count(),
        'cache' => DB::table('cache')->count(),
    ])->toBe($antes);
});

it('falla con un mensaje claro si el usuario no existe', function (): void {
    $this->artisan('operations:diagnose-coordination-table', ['--user' => 999_999_999, '--iterations' => 1])
        ->expectsOutputToContain('No se encontró el usuario')
        ->assertFailed();
});

it('mide la misma consulta que la tabla', function (): void {
    $comando = file_get_contents(base_path('app/Console/Commands/Operations/DiagnoseCoordinationTablePerformanceCommand.php'));
    $tabla = file_get_contents(base_path('app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php'));

    expect($comando)->toContain('OperationCoordinationServicesTable::listEagerLoads()')
        ->toContain('DB::rollBack()')
        ->toContain("config(['cache.default' => 'array'])")
        ->and($tabla)->toContain('return $query->with(self::listEagerLoads());');
});
