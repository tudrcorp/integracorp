<?php

declare(strict_types=1);

it('aplica estilo de afiliaciones al formulario de préstamos RRHH', function (): void {
    $formPath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhPrestamos/Schemas/RrhhPrestamoForm.php';
    $contents = file_get_contents($formPath);

    expect($contents)
        ->toContain('SECTION_CARD')
        ->toContain('INNER_CARD')
        ->toContain('TABS_CONTAINER')
        ->toContain("Tabs::make('rrhhPrestamoFormTabs')")
        ->toContain("Tab::make('Información principal')")
        ->toContain("Fieldset::make('Colaborador y descripción')")
        ->toContain("Fieldset::make('Condiciones del préstamo')")
        ->toContain("TextInput::make('interes')")
        ->toContain('Porcentaje de descuento')
        ->toContain("TextInput::make('monto_cuota')")
        ->toContain("Placeholder::make('validacion_cuotas')")
        ->toContain('RrhhPrestamoCuotaCalculo')
        ->toContain("Hidden::make('created_by')");
});

it('agrega columna interes a rrhh_prestamos via migracion', function (): void {
    $path = dirname(__DIR__, 2).'/database/migrations/2026_07_23_093344_add_interes_to_rrhh_prestamos_table.php';
    $contents = file_get_contents($path);

    expect(is_string($contents))->toBeTrue()
        ->and($contents)->toContain("hasColumn('rrhh_prestamos', 'interes')")
        ->and($contents)->toContain("->decimal('interes', 8, 2)->default(0)");
});

it('aplica alcance por departamento o colaborador en asignaciones RRHH', function (): void {
    $formPath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhAsignacions/Schemas/RrhhAsignacionForm.php';
    $tablePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhAsignacions/Tables/RrhhAsignacionsTable.php';
    $modelPath = dirname(__DIR__, 2).'/app/Models/RrhhAsignacion.php';

    $form = file_get_contents($formPath);
    $table = file_get_contents($tablePath);
    $model = file_get_contents($modelPath);

    expect($form)
        ->toContain('SECTION_CARD')
        ->toContain('INNER_CARD')
        ->toContain('TABS_CONTAINER')
        ->toContain("ToggleButtons::make('aplicacion')")
        ->toContain("'departamento' => 'Departamento'")
        ->toContain("'colaborador' => 'Colaborador'")
        ->toContain("Select::make('departamento_id')")
        ->toContain("Select::make('colaborador_id')")
        ->toContain("->relationship('departamento', 'description'")
        ->toContain("->relationship('colaborador', 'fullName'")
        ->toContain("Fieldset::make('Aplicación')")
        ->not->toContain("Select::make('cargo_id')");

    expect($table)
        ->toContain("TextColumn::make('aplicacion')")
        ->toContain("TextColumn::make('destino')")
        ->toContain('destinoLabel')
        ->toContain('por departamento o colaborador');

    expect($model)
        ->toContain("'aplicacion'")
        ->toContain("'tipo_valor'")
        ->toContain("'porcentaje'")
        ->toContain("'departamento_id'")
        ->toContain("'colaborador_id'")
        ->toContain('function departamento()')
        ->toContain('function colaborador()')
        ->toContain('function destinoLabel()')
        ->toContain('function valorLabel()')
        ->toContain('function calcularSobreSueldoBase');
});

it('aplica alcance por departamento o colaborador en deducciones RRHH', function (): void {
    $formPath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhDeduccions/Schemas/RrhhDeduccionForm.php';
    $tablePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhDeduccions/Tables/RrhhDeduccionsTable.php';
    $modelPath = dirname(__DIR__, 2).'/app/Models/RrhhDeduccion.php';

    $form = file_get_contents($formPath);
    $table = file_get_contents($tablePath);
    $model = file_get_contents($modelPath);

    expect($form)
        ->toContain('SECTION_CARD')
        ->toContain('INNER_CARD')
        ->toContain('TABS_CONTAINER')
        ->toContain("ToggleButtons::make('aplicacion')")
        ->toContain("'departamento' => 'Departamento'")
        ->toContain("'colaborador' => 'Colaborador'")
        ->toContain("Select::make('departamento_id')")
        ->toContain("Select::make('colaborador_id')")
        ->toContain("->relationship('departamento', 'description'")
        ->toContain("->relationship('colaborador', 'fullName'")
        ->toContain("Fieldset::make('Aplicación')")
        ->not->toContain("Select::make('cargo_id')")
        ->not->toContain('Nombre de la Asignacion');

    expect($table)
        ->toContain("TextColumn::make('aplicacion')")
        ->toContain("TextColumn::make('destino')")
        ->toContain('destinoLabel')
        ->toContain('por departamento o colaborador');

    expect($model)
        ->toContain("'aplicacion'")
        ->toContain("'tipo_valor'")
        ->toContain("'porcentaje'")
        ->toContain("'departamento_id'")
        ->toContain("'colaborador_id'")
        ->toContain('function departamento()')
        ->toContain('function colaborador()')
        ->toContain('function destinoLabel()')
        ->toContain('function valorLabel()')
        ->toContain('function calcularSobreSueldoBase');
});

it('agrega columnas de aplicacion a asignaciones y deducciones via migracion', function (): void {
    $path = dirname(__DIR__, 2).'/database/migrations/2026_07_23_093737_add_aplicacion_scope_to_rrhh_asignacions_and_deduccions_tables.php';
    $contents = file_get_contents($path);

    expect(is_string($contents))->toBeTrue()
        ->and($contents)->toContain("hasColumn('rrhh_asignacions', 'aplicacion')")
        ->and($contents)->toContain("hasColumn('rrhh_deduccions', 'aplicacion')")
        ->and($contents)->toContain("hasColumn('rrhh_asignacions', 'departamento_id')")
        ->and($contents)->toContain("hasColumn('rrhh_deduccions', 'colaborador_id')")
        ->and($contents)->toContain('->nullable()->change()');
});
