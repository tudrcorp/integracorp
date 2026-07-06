<?php

declare(strict_types=1);

use App\Enums\CorporateAgendaDepartment;

it('define los departamentos disponibles para la agenda corporativa', function (): void {
    expect(CorporateAgendaDepartment::values())->toBe([
        'comercial',
        'afiliaciones',
        'proveedores',
        'operaciones',
        'marketing',
        'proyecto',
        'administracion',
    ]);

    expect(CorporateAgendaDepartment::options())->toHaveKeys(CorporateAgendaDepartment::values());
    expect(CorporateAgendaDepartment::Administracion->label())->toBe('Administración');
});

it('incluye columna department en actividades de agenda corporativa', function (): void {
    $migrationPath = dirname(__DIR__, 2).'/database/migrations/2026_06_22_012250_add_department_to_corporate_agenda_activities_table.php';
    $modelPath = dirname(__DIR__, 2).'/app/Models/CorporateAgendaActivity.php';
    $filtersPath = dirname(__DIR__, 2).'/resources/views/filament/business/pages/partials/corporate-agenda-header-filters.blade.php';

    expect(file_get_contents($migrationPath))
        ->toContain("->string('department')")
        ->toContain('corporate_agenda_activities');

    expect(file_get_contents($modelPath))
        ->toContain('CorporateAgendaDepartment')
        ->toContain("'department'");

    expect(file_get_contents($filtersPath))
        ->toContain('corporateAgendaFilterDepartment')
        ->toContain('Todos los departamentos')
        ->not->toContain('corporateAgendaFilterActivityType');
});
