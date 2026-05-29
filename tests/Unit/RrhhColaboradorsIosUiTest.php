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
        ->toContain('IOS_SELECT_MATCH_INPUT_HEIGHT_CLASS')
        ->toContain('[&_.fi-select-input-btn]:!min-h-11')
        ->toContain("Tabs::make('rrhhColaboradorFormTabs')")
        ->toContain('->persistTab()')
        ->toContain("Tab::make('Perfil')")
        ->toContain("Tab::make('Datos personales')")
        ->toContain("Tab::make('Datos laborales')")
        ->toContain("Tab::make('Contacto')")
        ->toContain("Tab::make('Datos bancarios')")
        ->toContain("Tab::make('Documentos')")
        ->toContain("Textarea::make('funciones')")
        ->toContain('Funciones del colaborador')
        ->toContain("FileUpload::make('documents')")
        ->toContain('->multiple()')
        ->toContain("Section::make('Perfil')")
        ->toContain("Section::make('Datos personales')")
        ->toContain("Section::make('Datos laborales')")
        ->toContain("Section::make('Contacto')")
        ->toContain("Section::make('Datos bancarios')")
        ->toContain("Section::make('Documentos')")
        ->toContain("DatePicker::make('birth_date')")
        ->toContain('->maxDate(now())')
        ->toContain("'before_or_equal:today'")
        ->toContain("TextInput::make('age')")
        ->toContain('afterStateUpdated');

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
        ->toContain('DeleteAction::make()')
        ->toContain('mutateFormDataBeforeFill')
        ->toContain('completedYearsFromBirthDate');

    expect($profileModalContents)
        ->toContain('Colaborador')
        ->toContain('Contexto laboral')
        ->toContain('avatar')
        ->toContain('rounded-3xl');
});

it('agrega columna documents nullable en rrhh_colaboradors via migracion', function (): void {
    $path = dirname(__DIR__, 2).'/database/migrations/2026_05_14_081642_add_documents_to_rrhh_colaboradors_table.php';
    $contents = file_get_contents($path);

    expect(is_string($contents))->toBeTrue()
        ->and($contents)->toContain("hasColumn('rrhh_colaboradors', 'documents')")
        ->and($contents)->toContain("->json('documents')->nullable()");
});

it('agrega funciones longtext a rrhh_colaboradors via migracion', function (): void {
    $path = dirname(__DIR__, 2).'/database/migrations/2026_05_28_111606_add_funciones_to_rrhh_colaboradors_table.php';
    $contents = file_get_contents($path);

    expect(is_string($contents))->toBeTrue()
        ->and($contents)->toContain("hasColumn('rrhh_colaboradors', 'funciones')")
        ->and($contents)->toContain("->longText('funciones')->nullable()");
});

it('agrega birth_date y age a rrhh_colaboradors via migracion', function (): void {
    $path = dirname(__DIR__, 2).'/database/migrations/2026_05_14_082616_add_birth_date_and_age_to_rrhh_colaboradors_table.php';
    $contents = file_get_contents($path);

    expect(is_string($contents))->toBeTrue()
        ->and($contents)->toContain("hasColumn('rrhh_colaboradors', 'birth_date')")
        ->and($contents)->toContain("hasColumn('rrhh_colaboradors', 'age')")
        ->and($contents)->toContain("->date('birth_date')->nullable()")
        ->and($contents)->toContain("->unsignedSmallInteger('age')->nullable()");
});
