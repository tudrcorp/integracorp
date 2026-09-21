<?php

declare(strict_types=1);

use App\Filament\Shared\Renovations\Widgets\CorporateRenovationKpisWidget;
use App\Filament\Shared\Renovations\Widgets\IndividualRenovationKpisWidget;
use App\Filament\Shared\Renovations\Widgets\RenovationKpisWidget;
use Filament\Widgets\StatsOverviewWidget;

it('colapsa por defecto las secciones de retención y eficiencia', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/Renovations/Widgets/RenovationKpisWidget.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/shared/renovations/kpi-acceptors-table.blade.php');

    expect($source)
        ->toContain('->collapsible()')
        ->toContain('->collapsed()')
        ->toContain('scopeHeading')
        ->toContain('Retención y negocio')
        ->toContain('Eficiencia operativa')
        ->toContain('$isDiscovered = false')
        ->toContain('kpi-acceptors-table')
        ->toContain('acceptedLabel')
        ->not->toContain('kpi-portfolio-chart')
        ->not->toContain('kpi-efficiency-charts')
        ->not->toContain('CSAT')
        ->not->toContain('reclamacion')
        ->and(substr_count($source, '->collapsed()'))->toBe(2)
        ->and($view)
        ->toContain('Empleado')
        ->toContain('Prima retenida');

    $provider = file_get_contents(dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php');

    expect($provider)
        ->toContain("Livewire::component('app.filament.shared.renovations.widgets.individual-renovation-kpis-widget', IndividualRenovationKpisWidget::class)")
        ->toContain("Livewire::component('app.filament.shared.renovations.widgets.corporate-renovation-kpis-widget', CorporateRenovationKpisWidget::class)");
});

it('distingue widgets individuales y corporativos y no se auto-descubren en otros paneles', function (): void {
    $individual = new ReflectionClass(IndividualRenovationKpisWidget::class);
    $corporate = new ReflectionClass(CorporateRenovationKpisWidget::class);

    expect($individual->isSubclassOf(RenovationKpisWidget::class))->toBeTrue()
        ->and($corporate->isSubclassOf(RenovationKpisWidget::class))->toBeTrue()
        ->and(is_subclass_of(RenovationKpisWidget::class, StatsOverviewWidget::class))->toBeTrue()
        ->and(IndividualRenovationKpisWidget::isDiscovered())->toBeFalse()
        ->and(CorporateRenovationKpisWidget::isDiscovered())->toBeFalse()
        ->and(IndividualRenovationKpisWidget::getSort())->toBe(0)
        ->and(CorporateRenovationKpisWidget::getSort())->toBe(1);

    $individualFlag = $individual->getMethod('isCorporate');
    $individualFlag->setAccessible(true);
    $corporateFlag = $corporate->getMethod('isCorporate');
    $corporateFlag->setAccessible(true);

    expect($individualFlag->invoke($individual->newInstanceWithoutConstructor()))->toBeFalse()
        ->and($corporateFlag->invoke($corporate->newInstanceWithoutConstructor()))->toBeTrue();
});

it('registra los kpi en el dashboard principal de administración', function (): void {
    $administration = file_get_contents(dirname(__DIR__, 2).'/app/Providers/Filament/AdministrationPanelProvider.php');
    $business = file_get_contents(dirname(__DIR__, 2).'/app/Providers/Filament/BusinessPanelProvider.php');

    expect($administration)
        ->toContain('IndividualRenovationKpisWidget::class')
        ->toContain('CorporateRenovationKpisWidget::class')
        ->toContain('WelcomeUserLiquidGlassWidget::class')
        ->and($business)
        ->not->toContain('IndividualRenovationKpisWidget')
        ->not->toContain('CorporateRenovationKpisWidget');
});

it('no monta los kpi en los listados de renovación', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Renovations/Pages/ListRenovations.php'))
        ->not->toContain('getHeaderWidgets')
        ->not->toContain('IndividualRenovationKpisWidget')
        ->and(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Renovations/Pages/ListRenovations.php'))
        ->not->toContain('getHeaderWidgets')
        ->and(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/RenovationCorporates/Pages/ListRenovationCorporates.php'))
        ->not->toContain('getHeaderWidgets')
        ->and(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RenovationCorporates/Pages/ListRenovationCorporates.php'))
        ->not->toContain('getHeaderWidgets');
});
