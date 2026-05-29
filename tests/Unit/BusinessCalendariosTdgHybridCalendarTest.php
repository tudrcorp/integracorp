<?php

declare(strict_types=1);

it('define enums catalogos y tablas para la agenda hibrida tdg', function (): void {
    $migrationPath = dirname(__DIR__, 2).'/database/migrations/2026_05_22_004300_create_tdg_calendar_tables.php';
    $pagePath = dirname(__DIR__, 2).'/app/Filament/Business/Pages/CalendariosTdg.php';
    $traitPath = dirname(__DIR__, 2).'/app/Filament/Business/Pages/Concerns/InteractsWithTdgHybridCalendar.php';
    $modalPath = dirname(__DIR__, 2).'/resources/views/filament/business/pages/partials/calendarios-tdg-day-modal.blade.php';

    expect(file_get_contents($migrationPath))
        ->toContain("Schema::create('tdg_calendar_days'")
        ->toContain("Schema::create('tdg_calendar_office_assignments'")
        ->toContain("Schema::create('tdg_calendar_guard_assignments'")
        ->toContain("Schema::create('tdg_calendar_department_assignments'");

    expect(file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_05_28_122847_create_tdg_calendar_department_colaborador_assignments_table.php'))
        ->toContain('tdg_calendar_department_colaborador_assignments')
        ->toContain('tdg_dept_colab_day_dept_unique');

    expect(file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_05_22_010355_allow_multiple_colaboradores_per_tdg_calendar_office.php'))
        ->toContain('tdg_calendar_office_day_office_colaborador_unique');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Enums/TdgCalendarOffice.php'))
        ->toContain("case CentralLido = 'central_lido'")
        ->toContain('Farmadoc (Las Delicias)')
        ->toContain('Farmadoc (San Bernardino)');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Enums/TdgCalendarGuardShift.php'))
        ->toContain('2.1 8AM-5PM PROVEEDORES - 24H@TUDRENCASA.COM')
        ->toContain('2.2 8AM-5PM ILS/CAPITADO - 24H@TUDRENCASA.COM')
        ->toContain("case Nocturna = 'nocturna'")
        ->toContain('3.0 GUARDIA NOCTURNA')
        ->toContain('isNocturnalShift');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Enums/TdgCalendarDepartment.php'))
        ->toContain("case Comercial = 'comercial'")
        ->toContain("case Afiliaciones = 'afiliaciones'")
        ->toContain("case Marketing = 'marketing'")
        ->toContain("case Proyecto = 'proyecto'");

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/TdgCalendarDepartmentCatalog.php'))
        ->toContain('TdgCalendarDepartmentCatalog')
        ->toContain('modifier')
        ->toContain('tdg-dept-chip--')
        ->toContain("'comercial'")
        ->toContain("'color' =>");

    expect(file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css'))
        ->toContain('.dark .tdg-dept-chip--comercial.is-idle')
        ->toContain('.dark .tdg-dept-chip--marketing.is-selected');

    expect(file_get_contents($pagePath))
        ->toContain('InteractsWithTdgHybridCalendar');

    expect(file_get_contents($traitPath))
        ->toContain('saveDayAssignments')
        ->toContain('toggleDepartment')
        ->toContain('assignOfficeCollaborator')
        ->toContain('removeOfficeCollaborator')
        ->toContain('resolveSelectedOfficeCollaborators')
        ->toContain('isOfficeCollaboratorSelected')
        ->toContain('filteredCollaboratorOptionsForOffice')
        ->toContain('filteredCollaboratorOptionsForGuardShift')
        ->toContain('colaboradorIdsAssignedToOtherGuardShifts')
        ->toContain('colaboradorIdsAssignedToOtherOffices')
        ->toContain('removeColaboradorFromOtherOffices')
        ->toContain('replicateOfficeAssignmentsToSelectedDays')
        ->toContain('replicateGuardAssignmentsToSelectedDays')
        ->toContain('replicateDepartmentAssignmentsToSelectedDays')
        ->toContain('assignDepartmentCollaborator')
        ->toContain('removeDepartmentCollaborator')
        ->toContain('resolveSelectedDepartmentCollaborators')
        ->toContain('filteredCollaboratorOptionsForDepartment')
        ->toContain('getSystemsCollaboratorOptionsProperty')
        ->toContain('querySystemsColaboradores')
        ->toContain('departmentCollaboratorAssignmentsForm')
        ->toContain('syncDepartmentCollaboratorAssignments')
        ->toContain('buildDepartmentColaboradorAvatarsFromPayload')
        ->toContain('getSystemsColaboradorFilterOptionsProperty')
        ->toContain('updatedAgendaFilterSystemsColaborador')
        ->toContain('department_colaborador_assignments')
        ->toContain('agendaFilterSystemsColaborador')
        ->toContain('resolveAgendaFilterSystemsColaboradorId')
        ->toContain('dayMatchesDepartmentAgendaFilters')
        ->toContain('toggleOfficeReplicationDate')
        ->toContain('toggleGuardReplicationDate')
        ->toContain('toggleDepartmentReplicationDate')
        ->toContain('toggleOfficeReplicationWeekday')
        ->toContain('toggleGuardReplicationWeekday')
        ->toContain('getOfficeReplicationCalendarDaysProperty')
        ->toContain('getGuardReplicationCalendarDaysProperty')
        ->toContain('getDepartmentReplicationCalendarDaysProperty')
        ->toContain('isDaytimeOperationsShift')
        ->toContain('Un colaborador no puede asistir a más de una oficina')
        ->toContain('assignGuardCollaborator')
        ->toContain('agendaFilterCategory')
        ->toContain('resolveAgendaFilterCategory')
        ->toContain('scopeDayPayloadToAgendaFilterCategory')
        ->toContain('agendaFilterOffice')
        ->toContain('clearAgendaFilters')
        ->toContain('avatar_url')
        ->toContain('department_badges')
        ->toContain('TDG_CALENDAR_AVATAR_VISIBLE_LIMIT')
        ->toContain('presentColaboradorAvatarsForDayDisplay')
        ->toContain('buildOfficeFilterAvatarsFromPayload')
        ->toContain('avatars_overflow')
        ->toContain('office_count')
        ->toContain('avatars_tooltip')
        ->toContain('usesDepartmentFullLabelsInCalendar')
        ->toContain("? 'full' : 'short'")
        ->toContain('task_primary');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/TdgCalendarDepartmentCatalog.php'))
        ->toContain('display_label');

    expect(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/pages/partials/corporate-calendar-shell.blade.php'))
        ->toContain('$isTdgCalendar')
        ->toContain('display_label')
        ->toContain('Colaboradores de sistemas')
        ->toContain('filled($day[\'task_primary\'])');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/TdgCalendarOfficeCatalog.php'))
        ->toContain('TdgCalendarOfficeCatalog')
        ->toContain('LIDO')
        ->toContain('farmadoc-delicias');

    expect(file_get_contents(dirname(__DIR__, 2).'/resources/views/components/collaborator-avatar-stack.blade.php'))
        ->toContain('overflowCount')
        ->toContain('+{{ $overflowCount }}')
        ->toContain('tdg-calendar-avatar-stack__tooltip-list')
        ->toContain('overflow-y-auto');

    expect(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/pages/partials/tdg-calendar-day-avatars.blade.php'))
        ->toContain('x-collaborator-avatar-stack')
        ->toContain('tooltipLines')
        ->toContain('Colaboradores del día');

    expect(file_get_contents($modalPath))
        ->toContain('setModalWorkspace')
        ->toContain('filteredCollaboratorOptionsForGuardShift')
        ->toContain('guardia nocturna se asigna de forma independiente')
        ->toContain('assignOfficeCollaborator')
        ->toContain('removeOfficeCollaborator')
        ->toContain('lg:grid-cols-3')
        ->toContain('Guardia nocturna')
        ->toContain('tdg-guard-replication-panel')
        ->toContain('Colaboradores asignados')
        ->toContain('tdg-colaborador-avatar')
        ->toContain('toggleDepartment')
        ->toContain('assignDepartmentCollaborator')
        ->toContain('filteredCollaboratorOptionsForDepartment')
        ->toContain('Colaboradores de sistemas')
        ->toContain('tdg-department-replication-panel')
        ->toContain('idle_chip_class')
        ->toContain('saveDayAssignments');

    expect(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/pages/partials/tdg-office-replication-panel.blade.php'))
        ->toContain('replicateOfficeAssignmentsToSelectedDays')
        ->toContain('toggleOfficeReplicationDate')
        ->toContain('toggleOfficeReplicationWeekday')
        ->toContain('officeReplicationCalendarDays')
        ->toContain('Replicar configuración');

    expect(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/pages/partials/tdg-guard-replication-panel.blade.php'))
        ->toContain('replicateGuardAssignmentsToSelectedDays')
        ->toContain('toggleGuardReplicationDate')
        ->toContain('guardReplicationCalendarDays')
        ->toContain('Replicar guardias');

    expect(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/pages/partials/tdg-department-replication-panel.blade.php'))
        ->toContain('replicateDepartmentAssignmentsToSelectedDays')
        ->toContain('toggleDepartmentReplicationDate')
        ->toContain('departmentReplicationCalendarDays')
        ->toContain('Replicar departamentos');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Models/TdgCalendarDepartmentColaboradorAssignment.php'))
        ->toContain('TdgCalendarDepartmentColaboradorAssignment');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Models/TdgCalendarDay.php'))
        ->toContain('departmentColaboradorAssignments');

    expect(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/pages/partials/tdg-calendar-header-filters.blade.php'))
        ->toContain('agendaFilterCategory')
        ->toContain('Solo guardias')
        ->toContain('Solo oficinas')
        ->toContain('Solo departamentos')
        ->toContain('agendaFilterOffice')
        ->toContain('agendaFilterGuardShift')
        ->toContain('agendaFilterDepartment')
        ->toContain('agendaFilterSystemsColaborador')
        ->toContain('systemsColaboradorFilterOptions')
        ->toContain('Colaborador de sistemas')
        ->toContain('departmentFilterOptions');
});
