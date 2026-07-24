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
        ->toContain('TABS_CONTAINER')
        ->toContain('SECTION_CARD')
        ->toContain('INNER_CARD')
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
        ->toContain("Fieldset::make('Perfil del colaborador')")
        ->toContain("Fieldset::make('Identidad personal')")
        ->toContain("Fieldset::make('Información laboral')")
        ->toContain("Fieldset::make('Canales de contacto')")
        ->toContain("Fieldset::make('Cuenta bancaria')")
        ->toContain("Fieldset::make('Expediente documental')")
        ->toContain("Fieldset::make('Funciones del puesto')")
        ->toContain("Fieldset::make('Datos de identificación')")
        ->toContain("DatePicker::make('birth_date')")
        ->toContain('->maxDate(now())')
        ->toContain("'before_or_equal:today'")
        ->toContain("TextInput::make('age')")
        ->toContain('afterStateUpdated')
        ->toContain('prefixIcon')
        ->toContain('public static function avatarUploadField');

    $resourcePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhColaboradors/RrhhColaboradorResource.php';
    $resourceContents = file_get_contents($resourcePath);

    expect($resourceContents)
        ->toContain('AsignacionesRelationManager::class')
        ->toContain('DeduccionesRelationManager::class');

    $themePath = dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css';
    $themeContents = file_get_contents($themePath);

    expect($themeContents)
        ->toContain('.rrhh-colaborador-avatar-upload .fi-fo-file-upload-editor')
        ->toContain('fi-fo-file-upload-editor-control-panel-main')
        ->toContain('max-w-lg');

    expect($editPageContents)
        ->toContain("Action::make('update_avatar')")
        ->toContain('avatarUploadField')
        ->toContain('AUDIT_ADMIN_RRHH_COLABORADOR_AVATAR_UPDATED');

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
