<?php

declare(strict_types=1);

it('Telemedicina TelemedicineCasesTable usa consulta centralizada y estilo iOS del escritorio', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineCases/Tables/TelemedicineCasesTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('TelemedicineCaseFilamentListQuery::applyTelemedicinaResourceCasesConstraints')
        ->and($contents)->toContain('telemedicine-case-table-ios')
        ->and($contents)->toContain('TelemedicinePriorityFilamentBadge::')
        ->and($contents)->toContain('->emptyStateHeading(')
        ->and($contents)->toContain('->with([')
        ->and($contents)->toContain('telemedicine-case-address-column')
        ->and($contents)->toContain("TextColumn::make('patient_address')")
        ->and($contents)->not->toContain('max-w-[12rem]');
});

it('TelemedicineCaseFilamentListQuery aplica managed_by ATENMEDI con contexto médico ATENMEDI', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineCaseFilamentListQuery.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('self::userIsInAtenmediTelemedicinaContext($user)')
        ->and($contents)->toContain("->where('managed_by', 'ATENMEDI')");
});

it('TelemedicineCaseFilamentListQuery lista para TDG todos los casos de doctores TDG sin filtrar por doctor en sesión', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineCaseFilamentListQuery.php';
    $contents = file_get_contents($path);

    $resourceMethodStart = (int) strpos($contents, 'function applyTelemedicinaResourceCasesConstraints');
    $dashboardMethodStart = (int) strpos($contents, 'function applyDashboardWidgetCaseConstraints');
    $resourceMethod = substr($contents, $resourceMethodStart, $dashboardMethodStart - $resourceMethodStart);

    $tdgBranchPos = strpos($resourceMethod, 'userIsInTdgTelemedicinaContext($user)');
    $ownDoctorFilterPos = strpos($resourceMethod, "where('telemedicine_doctor_id', \$user->doctor_id)");

    expect($resourceMethod)
        ->toContain('userIsInTdgTelemedicinaContext($user)')
        ->toContain('constrainToTdgDoctorsCases($query)')
        ->toContain("->where('status', '!=', 'ALTA MEDICA')");

    expect($tdgBranchPos)->not->toBeFalse()
        ->and($ownDoctorFilterPos)->not->toBeFalse()
        ->and($tdgBranchPos)->toBeLessThan($ownDoctorFilterPos);

    expect($contents)
        ->toContain("->where('managed_by', 'TDG')")
        ->toContain("\$doctor->where('managed_by', 'TDG')");
});

it('widget del escritorio filtra managed_by ATENMEDI con contexto médico ATENMEDI', function (): void {
    $widgetPath = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Widgets/TelemedicineCaseTableDash.php';
    $queryPath = dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineCaseFilamentListQuery.php';
    $contents = file_get_contents($queryPath);

    expect(file_get_contents($widgetPath))
        ->toContain('TelemedicineCaseFilamentListQuery::applyDashboardWidgetCaseConstraints');

    $widgetMethodStart = (int) strpos($contents, 'function applyDashboardWidgetCaseConstraints');
    $nextMethodStart = (int) strpos($contents, 'public static function atenmediUserBlockedFromUpdatingConsultation');
    $widgetMethod = substr($contents, $widgetMethodStart, $nextMethodStart - $widgetMethodStart);

    expect($widgetMethod)
        ->toContain('userIsInAtenmediTelemedicinaContext($user)')
        ->toContain("->where('managed_by', 'ATENMEDI')")
        ->not->toContain('userDepartmentsIncludeAtenmedi($user)');
});

it('las acciones de fila de casos en Telemedicina no incluyen editar paciente', function (): void {
    $root = dirname(__DIR__, 2);
    $widget = file_get_contents(
        $root.'/app/Filament/Telemedicina/Widgets/TelemedicineCaseTableDash.php'
    );
    $table = file_get_contents(
        $root.'/app/Filament/Telemedicina/Resources/TelemedicineCases/Tables/TelemedicineCasesTable.php'
    );

    expect($widget)
        ->not->toContain('EditTelemedicineCasePatientAction')
        ->not->toContain('Editar paciente');

    expect($table)
        ->not->toContain('EditTelemedicineCasePatientAction')
        ->not->toContain('Editar paciente');

    expect(file_exists(
        $root.'/app/Filament/Telemedicina/Resources/TelemedicineCases/Actions/EditTelemedicineCasePatientAction.php'
    ))->toBeFalse();
});

it('exclusiones por consulta alta y ambulancia solo aplican en contexto ATENMEDI', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineCaseFilamentListQuery.php';
    $contents = file_get_contents($path);

    $resourceMethodStart = (int) strpos($contents, 'function applyTelemedicinaResourceCasesConstraints');
    $dashboardMethodStart = (int) strpos($contents, 'function applyDashboardWidgetCaseConstraints');
    $resourceMethod = substr($contents, $resourceMethodStart, $dashboardMethodStart - $resourceMethodStart);

    expect($resourceMethod)
        ->toContain('self::excludeCasesHavingConsultationWithAltaMedica($query)')
        ->toContain('self::excludeCasesHavingConsultationWithTrasladoAmbulancia($query)')
        ->toContain('self::userIsInAtenmediTelemedicinaContext($user)');

    $atenmediPos = strpos($resourceMethod, 'userIsInAtenmediTelemedicinaContext($user)');
    $excludeAltaPos = strpos($resourceMethod, 'excludeCasesHavingConsultationWithAltaMedica($query)');
    $tdgReturnPos = strpos($resourceMethod, 'constrainToTdgDoctorsCases($query)');

    expect($atenmediPos)->not->toBeFalse()
        ->and($excludeAltaPos)->not->toBeFalse()
        ->and($tdgReturnPos)->not->toBeFalse()
        ->and($excludeAltaPos)->toBeGreaterThan($atenmediPos)
        ->and($tdgReturnPos)->toBeLessThan($atenmediPos);
});
