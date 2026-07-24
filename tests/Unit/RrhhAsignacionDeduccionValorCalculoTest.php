<?php

declare(strict_types=1);

use App\Support\Rrhh\RrhhValorCalculo;

it('calcula monto fijo sin alterar el valor', function (): void {
    expect(RrhhValorCalculo::calcular('monto', 10, null, 1000))->toBe(10.0);
});

it('calcula porcentaje sobre el sueldo base', function (): void {
    expect(RrhhValorCalculo::calcular('porcentaje', null, 23, 1000))->toBe(230.0);
});

it('formatea etiquetas de valor para monto y porcentaje', function (): void {
    expect(RrhhValorCalculo::valorLabel('monto', 10, null))->toBe('US$ 10.00')
        ->and(RrhhValorCalculo::valorLabel('porcentaje', null, 23))->toBe('23.00% s/sueldo base')
        ->and(RrhhValorCalculo::tipoLabel('monto'))->toBe('Monto fijo')
        ->and(RrhhValorCalculo::tipoLabel('porcentaje'))->toBe('Porcentaje');
});

it('expone campos de tipo_valor y porcentaje en formularios de asignaciones y deducciones', function (): void {
    $asignacionForm = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhAsignacions/Schemas/RrhhAsignacionForm.php'
    );
    $deduccionForm = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhDeduccions/Schemas/RrhhDeduccionForm.php'
    );
    $asignacionTable = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhAsignacions/Tables/RrhhAsignacionsTable.php'
    );
    $deduccionTable = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhDeduccions/Tables/RrhhDeduccionsTable.php'
    );
    $migration = file_get_contents(
        dirname(__DIR__, 2).'/database/migrations/2026_07_23_094218_add_tipo_valor_and_porcentaje_to_rrhh_asignacions_and_deduccions_tables.php'
    );

    foreach ([$asignacionForm, $deduccionForm] as $form) {
        expect($form)
            ->toContain("ToggleButtons::make('tipo_valor')")
            ->toContain("TextInput::make('monto')")
            ->toContain("TextInput::make('porcentaje')")
            ->toContain('Porcentaje sobre sueldo base')
            ->toContain('RrhhValorCalculo::TIPO_MONTO')
            ->toContain('RrhhValorCalculo::TIPO_PORCENTAJE');
    }

    expect($asignacionTable)
        ->toContain("TextColumn::make('tipo_valor')")
        ->toContain("TextColumn::make('valor')")
        ->toContain('valorLabel');

    expect($deduccionTable)
        ->toContain("TextColumn::make('tipo_valor')")
        ->toContain("TextColumn::make('valor')")
        ->toContain('valorLabel');

    expect($migration)
        ->toContain("hasColumn('rrhh_asignacions', 'tipo_valor')")
        ->toContain("hasColumn('rrhh_deduccions', 'porcentaje')")
        ->toContain("->decimal('porcentaje', 8, 2)->nullable()");
});
