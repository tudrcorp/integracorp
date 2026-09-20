<?php

declare(strict_types=1);

use App\Models\OperationCoordinationService;
use App\Support\Telemedicine\Scopes\TelemedicineCaseTablePresence;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Esquema parcial en sqlite: la tabla hija existe y `telemedicine_cases` no.
 *
 * Es el escenario de varios tests del repo, y el que destapó dos fallos del
 * scope: la subconsulta contra una tabla ausente, y la recursión infinita al
 * pedir la conexión al builder en vez de al modelo.
 */
beforeEach(function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
    ]);

    DB::purge('sqlite');
    DB::reconnect('sqlite');
    TelemedicineCaseTablePresence::flush();

    Schema::dropIfExists('operation_coordination_services');
    Schema::create('operation_coordination_services', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('telemedicine_case_id')->nullable();
        $table->string('status')->nullable();
        $table->timestamps();
    });
});

afterEach(fn () => TelemedicineCaseTablePresence::flush());

it('consulta las trazas sin filtro cuando la tabla de casos no existe', function (): void {
    OperationCoordinationService::query()->create([
        'telemedicine_case_id' => 10,
        'status' => 'PENDIENTE',
    ]);

    expect(OperationCoordinationService::query()->toSql())
        ->toBe('select * from "operation_coordination_services"')
        ->and(OperationCoordinationService::query()->count())->toBe(1);
});

it('vuelve a filtrar en cuanto la tabla de casos está disponible', function (): void {
    Schema::create('telemedicine_cases', function (Blueprint $table): void {
        $table->id();
        $table->string('status');
        $table->timestamps();
    });

    TelemedicineCaseTablePresence::flush();

    expect(OperationCoordinationService::query()->toSql())
        ->toContain('not exists')
        ->toContain('telemedicine_cases');
});
