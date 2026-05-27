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
        ->and($contents)->toContain('->with([');
});

it('TelemedicineCaseFilamentListQuery aplica managed_by ATENMEDI con contexto médico ATENMEDI', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineCaseFilamentListQuery.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('self::userIsInAtenmediTelemedicinaContext($user)')
        ->and($contents)->toContain("->where('managed_by', 'ATENMEDI')");
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

it('exclusiones por consulta alta y ambulancia solo aplican en contexto ATENMEDI', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineCaseFilamentListQuery.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain(
        'if ($user !== null && self::userIsInAtenmediTelemedicinaContext($user)) {
            self::excludeCasesHavingConsultationWithAltaMedica($query);
            self::excludeCasesHavingConsultationWithTrasladoAmbulancia($query);
        }'
    );
});
