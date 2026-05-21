<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\TelemedicineDoctors\Schemas\TelemedicineDoctorForm;
use Filament\Schemas\Schema;

it('configura el formulario de médicos de telemedicina sin error', function (): void {
    $schema = Schema::make();
    $configured = TelemedicineDoctorForm::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('incluye selector de pertenencia entre ATENMEDI y TDG', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineDoctors/Schemas/TelemedicineDoctorForm.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Select::make('managed_by')")
        ->toContain("'ATENMEDI' => 'ATENMEDI'")
        ->toContain("'TDG' => 'TDG'");
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
