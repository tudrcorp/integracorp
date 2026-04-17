<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Agencies\Widgets\StatsOverviewAgency;

it('no declara vista propia: hereda el stats overview estándar de Filament como el escritorio', function (): void {
    $prop = (new ReflectionClass(StatsOverviewAgency::class))->getProperty('view');

    expect($prop->getDeclaringClass()->getName())->toBe(\Filament\Widgets\StatsOverviewWidget::class);
});

it('envuelve las stats en Section con filtros año/mes en afterHeader', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Widgets/StatsOverviewAgency.php';
    $code = file_get_contents($path);
    expect($code)->not->toBeFalse()
        ->and($code)->toContain('function content(Schema $schema): Schema')
        ->and($code)->toContain('$this->getSectionContentComponent()');
});

it('usa tres columnas para mantener las stats en una fila', function (): void {
    $method = new ReflectionMethod(StatsOverviewAgency::class, 'getColumns');
    $columns = $method->invoke(new StatsOverviewAgency);

    expect($columns)->toBe(3);
});

it('declara la clase CSS de glass en el tema (tarjetas sin contenedor section)', function (): void {
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');
    expect($css)->not->toBeFalse()
        ->and($css)->toContain('.fi-agency-stats-overview-glass')
        ->and($css)->toContain('.fi-agency-stats-overview-glass .fi-wi-stats-overview-stat');
});
