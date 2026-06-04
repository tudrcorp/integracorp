<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\TelemedicineDoctors\Schemas\TelemedicineDoctorForm;
use Filament\Schemas\Schema;

it('configura el formulario de médicos de telemedicina sin error', function (): void {
    $schema = Schema::make();
    $configured = TelemedicineDoctorForm::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('asocia el médico al proveedor del usuario y managed_by como texto en mayúsculas', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineDoctors/Schemas/TelemedicineDoctorForm.php';
    $modelPath = dirname(__DIR__, 2).'/app/Models/TelemedicineDoctor.php';
    $migrationPath = dirname(__DIR__, 2).'/database/migrations/2026_06_03_002011_add_supplier_id_to_telemedicine_doctors_table.php';

    expect(file_get_contents($path))
        ->toContain("Select::make('supplier_id')")
        ->toContain("->relationship('supplier', 'name')")
        ->toContain('Auth::user()?->supplier_id')
        ->toContain('->disabled()')
        ->toContain("TextInput::make('managed_by')")
        ->toContain('$state.toUpperCase()')
        ->not->toContain("'ATENMEDI' => 'ATENMEDI'");

    expect(file_get_contents($modelPath))
        ->toContain("'supplier_id'")
        ->toContain('function supplier()');

    expect(file_get_contents($migrationPath))
        ->toContain('supplier_id');
});

it('organiza el formulario en tabs con el mismo estilo del registro de agencias master', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineDoctors/Schemas/TelemedicineDoctorForm.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('Tabs::make(')
        ->toContain("Tab::make('Perfil del médico')")
        ->toContain("Tab::make('Credenciales profesionales')")
        ->toContain("Tab::make('Archivos')")
        ->toContain('private const TABS_CONTAINER')
        ->toContain('private const SECTION_CARD')
        ->toContain('extraAttributes([')
        ->toContain("'class' => self::TABS_CONTAINER")
        ->toContain("'class' => self::SECTION_CARD");
});
