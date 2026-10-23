<?php

declare(strict_types=1);

use App\Filament\Business\Resources\ProspectAgents\Widgets\ClassificationProspect;
use App\Filament\Business\Resources\ProspectAgents\Widgets\ReferenceProspect;
use App\Filament\Business\Resources\ProspectAgents\Widgets\TopRegisterProspect;
use App\Filament\Business\Resources\ProspectAgents\Widgets\TopRegisterProspectForState;
use App\Filament\Business\Resources\ProspectAgents\Widgets\TypeProspect;

it('los gráficos de barras de prospectos usan la vista y clase CSS al estilo agencias', function (): void {
    $view = 'filament.widgets.prospect-chart-agency-style';
    foreach ([ReferenceProspect::class, TypeProspect::class, ClassificationProspect::class] as $class) {
        $ref = new ReflectionClass($class);
        expect($ref->getDefaultProperties()['view'] ?? null)->toBe($view);
    }

    $blade = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/widgets/prospect-chart-agency-style.blade.php');
    expect($blade)->not->toBeFalse()
        ->and($blade)->toContain('fi-agency-registrations-chart-like-suppliers');
});

it('el gráfico por estado usa la misma vista agencia-style', function (): void {
    $ref = new ReflectionClass(TopRegisterProspectForState::class);
    expect($ref->getDefaultProperties()['view'] ?? null)
        ->toBe('filament.widgets.prospect-chart-agency-style');
});

it('el ranking por colaborador tiene drill-down de cambios de estatus', function (): void {
    $ref = new ReflectionClass(TopRegisterProspect::class);

    expect($ref->getDefaultProperties()['view'] ?? null)
        ->toBe('filament.widgets.top-register-prospect-chart')
        ->and($ref->hasMethod('handleChartClick'))->toBeTrue()
        ->and($ref->hasMethod('resetDrillDown'))->toBeTrue()
        ->and($ref->hasMethod('isDrillDown'))->toBeTrue();

    $src = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/ProspectAgents/Widgets/TopRegisterProspect.php');
    expect($src)->not->toBeFalse()
        ->and($src)->toContain('statusChangeCountsForCollaborator')
        ->and($src)->toContain('handleChartClick')
        ->and($src)->toContain('selectedCollaborator');

    $blade = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/widgets/top-register-prospect-chart.blade.php');
    expect($blade)->not->toBeFalse()
        ->and($blade)->toContain('wire:click="resetDrillDown"')
        ->and($blade)->toContain('Volver');
});

it('el listado de prospectos ya no registra el gráfico suelto de cambios de estatus', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/ProspectAgents/Pages/ListProspectAgents.php';
    $contents = file_get_contents($path);

    expect($contents)->not->toBeFalse()
        ->and($contents)->toContain('TopRegisterProspect::class')
        ->and($contents)->not->toContain('StatusChangesByMonth');

    expect(file_exists(dirname(__DIR__, 2).'/app/Filament/Business/Resources/ProspectAgents/Widgets/StatusChangesByMonth.php'))
        ->toBeFalse();
});
