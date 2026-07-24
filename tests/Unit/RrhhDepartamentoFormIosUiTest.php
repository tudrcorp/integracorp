<?php

declare(strict_types=1);

it('aplica estilo de afiliaciones al formulario de departamentos RRHH', function (): void {
    $formPath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhDepartamentos/Schemas/RrhhDepartamentoForm.php';
    $contents = file_get_contents($formPath);

    expect($contents)
        ->toContain('SECTION_CARD')
        ->toContain('INNER_CARD')
        ->toContain('TABS_CONTAINER')
        ->toContain("Tabs::make('rrhhDepartamentoFormTabs')")
        ->toContain("Tab::make('Información principal')")
        ->toContain("Fieldset::make('Identificación del departamento')")
        ->toContain("Fieldset::make('Datos generales')")
        ->toContain("TextInput::make('description')")
        ->toContain('Nombre del departamento')
        ->toContain('prefixIcon')
        ->toContain('heroicon-m-building-office-2')
        ->toContain('afterStateUpdatedJs')
        ->toContain("Hidden::make('created_by')")
        ->toContain("Hidden::make('updated_by')")
        ->not->toContain('Section::make');
});
