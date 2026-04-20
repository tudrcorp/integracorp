<?php

declare(strict_types=1);

it('aplica estilo iOS al formulario y acciones de colaboradores', function (): void {
    $formPath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhColaboradors/Schemas/RrhhColaboradorForm.php';
    $tablePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhColaboradors/Tables/RrhhColaboradorsTable.php';
    $listPagePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhColaboradors/Pages/ListRrhhColaboradors.php';
    $createPagePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhColaboradors/Pages/CreateRrhhColaborador.php';
    $editPagePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhColaboradors/Pages/EditRrhhColaborador.php';
    $profileModalPath = dirname(__DIR__, 2).'/resources/views/filament/administration/rrhh-colaboradors/colaborador-quick-profile.blade.php';

    $formContents = file_get_contents($formPath);
    $tableContents = file_get_contents($tablePath);
    $listPageContents = file_get_contents($listPagePath);
    $createPageContents = file_get_contents($createPagePath);
    $editPageContents = file_get_contents($editPagePath);
    $profileModalContents = file_get_contents($profileModalPath);

    expect($formContents)
        ->toContain('IOS_SECTION_CLASS')
        ->toContain("Section::make('Perfil')")
        ->toContain("Section::make('Datos personales')")
        ->toContain("Section::make('Datos laborales')")
        ->toContain("Section::make('Contacto')")
        ->toContain("Section::make('Datos bancarios')");

    expect($tableContents)
        ->toContain('IOS_PRIMARY_BUTTON_CLASS')
        ->toContain('IOS_DANGER_BUTTON_CLASS')
        ->toContain('ticket-btn-ios')
        ->toContain('aviso-btn-ios-danger')
        ->toContain('view_colaborador_profile')
        ->toContain('filament.administration.rrhh-colaboradors.colaborador-quick-profile')
        ->toContain('Perfil del Colaborador')
        ->toContain('EditAction::make()')
        ->toContain('DeleteBulkAction::make()');

    expect($listPageContents)
        ->toContain('ticket-btn-ios')
        ->toContain('CreateAction::make()');

    expect($createPageContents)
        ->toContain('ticket-btn-ios-gray')
        ->toContain('Volver al listado');

    expect($editPageContents)
        ->toContain('ticket-btn-ios-gray')
        ->toContain('aviso-btn-ios-danger')
        ->toContain('DeleteAction::make()');

    expect($profileModalContents)
        ->toContain('Colaborador')
        ->toContain('Contexto laboral')
        ->toContain('avatar')
        ->toContain('rounded-3xl');
});
