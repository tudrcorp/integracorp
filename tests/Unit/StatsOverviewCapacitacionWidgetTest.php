<?php

declare(strict_types=1);

use App\Filament\Business\Resources\ProspectAgents\Widgets\StatsOverviewCapacitacion;

it('usa vista glass iOS y una columna para la stat', function (): void {
    $ref = new ReflectionClass(StatsOverviewCapacitacion::class);

    expect($ref->getDefaultProperties()['view'] ?? null)
        ->toBe('filament.widgets.stats-overview-agency-glass');

    $columns = (new ReflectionMethod(StatsOverviewCapacitacion::class, 'getColumns'))
        ->invoke(new StatsOverviewCapacitacion);

    expect($columns)->toBe(1);
});

it('ocupa el ancho completo del área de widgets del listado', function (): void {
    $ref = new ReflectionClass(StatsOverviewCapacitacion::class);

    expect($ref->getDefaultProperties()['columnSpan'] ?? null)->toBe('full');
});

it('usa el mismo contenedor glass que las estadísticas de agencias', function (): void {
    $blade = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/widgets/stats-overview-agency-glass.blade.php');
    expect($blade)->not->toBeFalse()
        ->and($blade)->toContain('fi-agency-stats-overview-glass');
});
