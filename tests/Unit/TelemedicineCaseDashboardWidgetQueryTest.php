<?php

declare(strict_types=1);

it('widget TDG lista todos los casos de doctores TDG excepto alta medica', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineCaseFilamentListQuery.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('userIsInTdgTelemedicinaContext')
        ->toContain('constrainToTdgDoctorsCases')
        ->toContain("->where('status', '!=', 'ALTA MEDICA')")
        ->toContain("->where('managed_by', 'TDG')")
        ->toContain("->with(['telemedicineDoctor', 'priority'])")
        ->toContain('caseIsUnderAtenmediDoctor')
        ->toContain('dashboardUserCanInteractWithCase')
        ->toContain('notifyTdgCaseUnderAtenmediDoctor');
});

it('widget del escritorio bloquea modal y acciones TDG cuando el caso es ATENMEDI', function (): void {
    $widget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Widgets/TelemedicineCaseTableDash.php');

    expect($widget)
        ->toContain("Action::make('openCaseConsultations')")
        ->toContain('->action($openCaseConsultationsAction)')
        ->toContain('authorizationNotification')
        ->toContain('guardDashboardCaseInteraction')
        ->toContain('telemedicine-case-row-readonly')
        ->toContain('TelemedicinePriorityFilamentBadge::recordRowClasses');
});

it('widget del dashboard muestra telefono principal y de contacto', function (): void {
    $widget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Widgets/TelemedicineCaseTableDash.php');

    expect($widget)
        ->toContain("TextColumn::make('patient_phone')")
        ->toContain('Principal:')
        ->toContain('Contacto:')
        ->toContain("->searchable(['patient_phone', 'patient_phone_2'])");
});
