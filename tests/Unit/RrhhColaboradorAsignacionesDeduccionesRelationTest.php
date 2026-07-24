<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\RrhhColaboradors\RelationManagers\AsignacionesRelationManager;
use App\Filament\Administration\Resources\RrhhColaboradors\RelationManagers\DeduccionesRelationManager;
use App\Filament\Administration\Resources\RrhhColaboradors\RrhhColaboradorResource;

it('registra relation managers de asignaciones y deducciones en el colaborador', function (): void {
    expect(RrhhColaboradorResource::getRelations())
        ->toContain(AsignacionesRelationManager::class)
        ->toContain(DeduccionesRelationManager::class);
});

it('define relaciones hasMany de asignaciones y deducciones en el colaborador', function (): void {
    $path = dirname(__DIR__, 2).'/app/Models/RrhhColaborador.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('function asignaciones(): HasMany')
        ->toContain('function deducciones(): HasMany')
        ->toContain('function asignacionesAplicables(): Collection')
        ->toContain('function deduccionesAplicables(): Collection')
        ->toContain("hasMany(RrhhAsignacion::class, 'colaborador_id')")
        ->toContain("hasMany(RrhhDeduccion::class, 'colaborador_id')");
});

it('permite agregar y eliminar asignaciones desde el relation manager', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhColaboradors/RelationManagers/AsignacionesRelationManager.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("protected static string \$relationship = 'asignaciones'")
        ->toContain('CreateAction::make()')
        ->toContain('DeleteAction::make()')
        ->toContain('DeleteBulkAction::make()')
        ->toContain('EditAction::make()')
        ->toContain("\$data['aplicacion'] = 'colaborador'")
        ->toContain('RrhhColaboradorConceptoForm::components')
        ->toContain('Agregar asignación')
        ->toContain('Eliminar');
});

it('permite agregar y eliminar deducciones desde el relation manager', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhColaboradors/RelationManagers/DeduccionesRelationManager.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("protected static string \$relationship = 'deducciones'")
        ->toContain('CreateAction::make()')
        ->toContain('DeleteAction::make()')
        ->toContain('DeleteBulkAction::make()')
        ->toContain('EditAction::make()')
        ->toContain("\$data['aplicacion'] = 'colaborador'")
        ->toContain('RrhhColaboradorConceptoForm::components')
        ->toContain('Agregar deducción')
        ->toContain('Eliminar');
});

it('comparte el formulario de concepto entre asignaciones y deducciones del colaborador', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/Rrhh/RrhhColaboradorConceptoForm.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('class RrhhColaboradorConceptoForm')
        ->toContain("ToggleButtons::make('tipo_valor')")
        ->toContain("TextInput::make('monto')")
        ->toContain("TextInput::make('porcentaje')");
});
