<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\RrhhNominas\Pages\ViewRrhhNomina;
use App\Filament\Administration\Resources\RrhhNominas\RelationManagers\DetallesRelationManager;
use App\Filament\Administration\Resources\RrhhNominas\RrhhNominaResource;

it('registra la vista de detalle de nomina con relation manager', function (): void {
    expect(RrhhNominaResource::getPages())
        ->toHaveKey('view')
        ->and(RrhhNominaResource::getPages()['view']->getPage())->toBe(ViewRrhhNomina::class);

    expect(RrhhNominaResource::getRelations())
        ->toContain(DetallesRelationManager::class);
});

it('expone action ver detalle en la tabla de nominas', function (): void {
    $table = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhNominas/Tables/RrhhNominasTable.php'
    );

    expect($table)
        ->toContain('ViewAction::make()')
        ->toContain('Ver detalle');
});

it('configura el relation manager de detalles con desglose completo', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhNominas/RelationManagers/DetallesRelationManager.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("protected static string \$relationship = 'detalleNomina'")
        ->toContain("TextColumn::make('colaborador_nombre')")
        ->toContain('nombreColaborador()')
        ->toContain('nombreDepartamento()')
        ->toContain('nombreCargo()')
        ->toContain('colaborador.departamento')
        ->toContain("TextColumn::make('detalle_asignaciones')")
        ->toContain("TextColumn::make('detalle_descuentos')")
        ->toContain("TextColumn::make('detalle_prestamos')")
        ->toContain("TextColumn::make('monto_total')")
        ->toContain('RepeatableEntry::make')
        ->toContain('Ver desglose')
        ->toContain('isReadOnly');
});

it('resuelve nombre departamento y cargo desde el colaborador si el snapshot esta vacio', function (): void {
    $detalle = new App\Models\RrhhDetalleNomina([
        'colaborador_nombre' => null,
        'colaborador_cedula' => null,
        'departamento_nombre' => null,
        'cargo_nombre' => null,
        'salario' => 100,
        'salario_ves' => 0,
    ]);

    $colaborador = new App\Models\RrhhColaborador([
        'fullName' => 'ANA PEREZ',
        'cedula' => 'V123',
    ]);
    $colaborador->setRelation('departamento', new App\Models\RrhhDepartamento([
        'description' => 'OPERACIONES',
    ]));
    $colaborador->setRelation('cargo', new App\Models\RrhhCargo([
        'description' => 'ANALISTA',
    ]));
    $detalle->setRelation('colaborador', $colaborador);

    expect($detalle->nombreColaborador())->toBe('ANA PEREZ')
        ->and($detalle->cedulaColaborador())->toBe('V123')
        ->and($detalle->nombreDepartamento())->toBe('OPERACIONES')
        ->and($detalle->nombreCargo())->toBe('ANALISTA')
        ->and($detalle->montoVes('salario', 'salario_ves', 36.5))->toBe(3650.0);
});

it('persiste desglose json en el calculador de nomina', function (): void {
    $calculator = file_get_contents(dirname(__DIR__, 2).'/app/Support/Rrhh/RrhhNominaCalculator.php');

    expect($calculator)
        ->toContain("'detalle_asignaciones'")
        ->toContain("'detalle_descuentos'")
        ->toContain("'detalle_prestamos'")
        ->toContain("'colaborador_nombre'")
        ->toContain("'salario_ves'")
        ->toContain('sueldoDelPeriodo')
        ->toContain('mapConceptos')
        ->toContain('mapPrestamosActivos');
});

it('agrega columnas de desglose al detalle de nominas via migracion', function (): void {
    $migration = file_get_contents(
        dirname(__DIR__, 2).'/database/migrations/2026_07_23_101512_add_detail_breakdown_to_rrhh_detalle_nominas_table.php'
    );

    expect($migration)
        ->toContain("hasColumn('rrhh_detalle_nominas', 'detalle_asignaciones')")
        ->toContain("hasColumn('rrhh_detalle_nominas', 'detalle_descuentos')")
        ->toContain("hasColumn('rrhh_detalle_nominas', 'detalle_prestamos')")
        ->toContain("hasColumn('rrhh_detalle_nominas', 'colaborador_nombre')")
        ->toContain("->json('detalle_asignaciones')");
});
