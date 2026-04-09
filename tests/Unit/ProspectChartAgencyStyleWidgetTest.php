<?php

declare(strict_types=1);

use App\Filament\Business\Resources\ProspectAgents\Widgets\ReferenceProspect;
use App\Filament\Business\Resources\ProspectAgents\Widgets\TopRegisterProspect;
use App\Filament\Business\Resources\ProspectAgents\Widgets\TopRegisterProspectForState;
use App\Filament\Business\Resources\ProspectAgents\Widgets\TypeProspect;

it('los gráficos de barras de prospectos usan la vista y clase CSS al estilo agencias', function (): void {
    $view = 'filament.widgets.prospect-chart-agency-style';
    foreach ([TopRegisterProspect::class, ReferenceProspect::class, TypeProspect::class] as $class) {
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
