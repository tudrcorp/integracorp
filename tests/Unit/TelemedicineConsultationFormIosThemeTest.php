<?php

declare(strict_types=1);

it('el theme admin y el formulario de consulta telemedicina definen el wizard iOS', function (): void {
    $root = dirname(__DIR__, 2);
    $theme = file_get_contents($root.'/resources/css/filament/admin/theme.css');
    expect($theme)->toContain('.fi-telemedicine-consultation-wizard')
        ->and($theme)->toContain('.fi-telemedicine-case-status-section');

    $form = file_get_contents(
        $root.'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php'
    );
    expect($form)->toContain('WIZARD_IOS_CLASS')
        ->and($form)->toContain('fi-telemedicine-consultation-wizard')
        ->and($form)->toContain('fi-telemedicine-case-status-section');
});

it('el formulario de consulta no oculta la asignación de servicio ni la prioridad en la página de edición', function (): void {
    $root = dirname(__DIR__, 2);
    $form = file_get_contents(
        $root.'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php'
    );
    expect($form)->toContain("Select::make('telemedicine_priority_id')")
        ->and($form)->not->toContain("hiddenOn('edit')");
});

it('el formulario de consulta ya no incluye el select "Pertenece a?"', function (): void {
    $root = dirname(__DIR__, 2);
    $form = file_get_contents(
        $root.'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php'
    );

    expect($form)
        ->not->toContain("Select::make('belongs_to')")
        ->not->toContain("->label('Pertenece a?')");
});

it('la acción de asignar doctor agrega el select "Pertenece a?" visible solo para analistas TDG', function (): void {
    $root = dirname(__DIR__, 2);
    $action = file_get_contents(
        $root.'/app/Filament/Operations/Resources/TelemedicinePatients/Actions/AssignDoctorAction.php'
    );

    expect($action)
        ->toContain("Select::make('belongs_to')")
        ->toContain("->label('Pertenece a?')")
        ->toContain("'Diagnomovil' => 'Diagnomovil'")
        ->toContain("'Centro Diagnostico 3 de Febrero' => 'Centro Diagnostico 3 de Febrero'")
        ->toContain('TelemedicineDoctor::with(\'supplier\')')
        ->toContain('?->supplier?->name')
        ->toContain('OperationsSupplierScope::authenticatedUserIsTdgAnalyst()');
});

it('el modelo de consulta no persiste belongs_to; el dato vive en el caso de telemedicina', function (): void {
    $root = dirname(__DIR__, 2);

    expect(file_get_contents($root.'/app/Models/TelemedicineConsultationPatient.php'))
        ->not->toContain("'belongs_to'");

    expect(file_get_contents($root.'/app/Models/TelemedicineCase.php'))
        ->toContain("'belongs_to'");

    $createPage = file_get_contents(
        $root.'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php'
    );

    expect($createPage)->not->toContain("['belongs_to']");
});

it('el widget CaseStats usa contenedor iOS y el theme define sus tarjetas', function (): void {
    $root = dirname(__DIR__, 2);
    $theme = file_get_contents($root.'/resources/css/filament/admin/theme.css');
    expect($theme)->toContain('.fi-telemedicine-case-stats-ios')
        ->and($theme)->toContain('.fi-telemedicine-case-stat-ios--assigned')
        ->and($theme)->toContain('.fi-telemedicine-case-stat-ios--followup')
        ->and($theme)->toContain('.fi-telemedicine-case-stat-ios--discharge')
        ->and($theme)->toContain('backdrop-filter: blur(28px) saturate(200%)');

    $widget = file_get_contents($root.'/app/Filament/Telemedicina/Widgets/CaseStats.php');
    expect($widget)->toContain('fi-telemedicine-case-stats-ios')
        ->and($widget)->toContain('fi-telemedicine-case-stat-ios--assigned');
});

it('el dashboard de casos telemedicina deja el toolbar sin caja y el buscador solo con borde', function (): void {
    $root = dirname(__DIR__, 2);
    $theme = file_get_contents($root.'/resources/css/filament/admin/theme.css');
    expect($theme)->toContain('.telemedicine-case-table-ios .fi-ta-header-toolbar')
        ->and($theme)->toContain('border-0 bg-transparent p-0 shadow-none')
        ->and($theme)->toContain('.telemedicine-case-table-ios .fi-ta-search-field .fi-input-wrp')
        ->and($theme)->toContain('border border-zinc-200/90')
        ->and($theme)->toContain('dark:border-zinc-700/80');
});

it('las acciones del header de crear consulta usan clases iOS alineadas al color Filament', function (): void {
    $root = dirname(__DIR__, 2);
    $theme = file_get_contents($root.'/resources/css/filament/admin/theme.css');
    expect($theme)->toContain('.aviso-btn-ios-primary')
        ->and($theme)->toContain('.aviso-btn-ios-primary .fi-btn-icon')
        ->and($theme)->toContain('.dark .aviso-btn-ios-primary .fi-btn-icon')
        ->and($theme)->toContain('.aviso-btn-ios-warning')
        ->and($theme)->toContain('.ticket-btn-ios-gray');

    $page = file_get_contents(
        $root.'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php'
    );
    expect($page)->toContain('FilamentIosButton::extraClassForFilamentColor')
        ->and($page)->toContain("FilamentIosButton::extraClassForFilamentColor('estandar')")
        ->and($page)->toContain("FilamentIosButton::extraClassForFilamentColor('urgencia')")
        ->and($page)->toContain("FilamentIosButton::extraClassForFilamentColor('primary')");

    $form = file_get_contents(
        $root.'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php'
    );
    expect($form)->toContain('->previousAction(')
        ->and($form)->toContain('->nextAction(')
        ->and($form)->toContain("FilamentIosButton::extraClassForFilamentColor('gray')")
        ->and($form)->toContain("FilamentIosButton::extraClassForFilamentColor('primary')");
});
