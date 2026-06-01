<?php

declare(strict_types=1);

it('widget TDG lista todos los casos excepto alta medica sin filtrar por doctor', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineCaseFilamentListQuery.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('userIsInTdgTelemedicinaContext')
        ->toContain("->where('status', '!=', 'ALTA MEDICA')")
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
