<?php

declare(strict_types=1);

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentLabels;
use App\Filament\Business\Resources\ProspectAgents\Widgets\StatsOverviewCapacitacion;
use Filament\Widgets\StatsOverviewWidget;

it('ocupa el ancho completo del área de widgets del listado', function (): void {
    $ref = new ReflectionClass(StatsOverviewCapacitacion::class);

    expect($ref->getDefaultProperties()['columnSpan'] ?? null)->toBe('full');
});

it('extiende StatsOverviewWidget y define columnas para total más cinco estatus', function (): void {
    expect(is_subclass_of(StatsOverviewCapacitacion::class, StatsOverviewWidget::class))->toBeTrue();

    $columns = (new ReflectionMethod(StatsOverviewCapacitacion::class, 'getColumns'))
        ->invoke(new StatsOverviewCapacitacion);

    expect($columns)->toBe([
        'default' => 1,
        'md' => 2,
        'xl' => 3,
        '2xl' => 6,
    ]);
});

it('cuenta total de captación y los cinco estatus del embudo', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/ProspectAgents/Widgets/StatsOverviewCapacitacion.php');

    expect($src)->not->toBeFalse()
        ->and($src)->toContain("Stat::make('TOTAL DE CAPTACIÓN'")
        ->and($src)->toContain("Stat::make('CAPTACIÓN/PROSPECCIÓN'")
        ->and($src)->toContain("Stat::make('CONTACTO INICIAL'")
        ->and($src)->toContain("Stat::make('EN PROCESO/EN NEGOCIACIÓN'")
        ->and($src)->toContain("Stat::make('ALIADO ACTIVO'")
        ->and($src)->toContain("Stat::make('INACTIVO'")
        ->and($src)->toContain('ProspectAgentLabels::STATUS_CAPTACION_PROSPECCION')
        ->and($src)->toContain('ProspectAgentLabels::STATUS_CONTACTO_INICIAL')
        ->and($src)->toContain('ProspectAgentLabels::STATUS_EN_PROCESO_NEGOCIACION')
        ->and($src)->toContain('ProspectAgentLabels::STATUS_ALIADO_ACTIVO')
        ->and($src)->toContain('ProspectAgentLabels::STATUS_INACTIVO')
        ->and($src)->toContain('statusKeysIncludingLegacy');
});

it('está registrado en el listado de prospectos', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/ProspectAgents/Pages/ListProspectAgents.php';
    $contents = file_get_contents($path);

    expect($contents)->not->toBeFalse()
        ->and($contents)->toContain('StatsOverviewCapacitacion::class');
});

it('el default de estatus coincide con captación/prospección', function (): void {
    expect(ProspectAgentLabels::defaultStatus())->toBe('captación-prospección');
});
