<?php

declare(strict_types=1);

use App\Support\IndicadoresDeDesempeno\SupplierAcceptanceLettersChartSeries;
use App\Support\IndicadoresDeDesempeno\SupplierNewProviderCreationChartSeries;
use App\Support\IndicadoresDeDesempeno\SupplierObservationsChartSeries;
use App\Support\IndicadoresDeDesempeno\SupplierProviderSystemUpdateChartSeries;

it('define indicadores de desempeño como resource en operations con widget de tickets', function () {
    $resourcePath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/IndicadoresDeDesempeno/IndicadoresDeDesempenoResource.php';
    $pagePath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/IndicadoresDeDesempeno/Pages/ListIndicadoresDeDesempeno.php';
    $widgetPath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/IndicadoresDeDesempeno/Widgets/ColaboradoresHelpdeskTicketsChart.php';
    $seriesPath = dirname(__DIR__, 2).'/app/Support/IndicadoresDeDesempeno/ColaboradoresHelpdeskTicketsChartSeries.php';
    $panelProviderPath = dirname(__DIR__, 2).'/app/Providers/Filament/OperationsPanelProvider.php';
    $appServiceProviderPath = dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php';

    expect(file_exists($resourcePath))->toBeTrue()
        ->and(file_exists($pagePath))->toBeTrue()
        ->and(file_exists($widgetPath))->toBeTrue()
        ->and(file_exists($seriesPath))->toBeTrue();

    $resourceContents = file_get_contents($resourcePath);
    $pageContents = file_get_contents($pagePath);
    $widgetContents = file_get_contents($widgetPath);
    $panelProviderContents = file_get_contents($panelProviderPath);
    $calendariosTdgPath = dirname(__DIR__, 2).'/app/Filament/Operations/Pages/CalendariosTdg.php';

    expect($resourceContents)->toContain("protected static ?string \$navigationLabel = 'Indicadores de desempeño';")
        ->toContain('protected static ?int $navigationSort = 4;')
        ->toContain("protected static ?string \$slug = 'indicadores-de-desempeno';")
        ->toContain("'index' => ListIndicadoresDeDesempeno::route('/')");

    expect($pageContents)->toContain('ColaboradorActivitiesSpeedometerWidget::class')
        ->toContain('IndicadoresDeDesempenoChartsTabsWidget::class')
        ->not->toContain('ColaboradoresHelpdeskTicketsChart::class')
        ->not->toContain('SupplierObservationsChart::class')
        ->not->toContain('SupplierProviderSystemUpdateChart::class')
        ->not->toContain('SupplierNewProviderCreationChart::class')
        ->not->toContain('SupplierAcceptanceLettersChart::class');

    $chartsTabsWidgetPath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/IndicadoresDeDesempeno/Widgets/IndicadoresDeDesempenoChartsTabsWidget.php';
    $chartsTabsViewPath = dirname(__DIR__, 2).'/resources/views/filament/operations/indicadores-de-desempeno-charts-tabs.blade.php';
    $chartTabsPath = dirname(__DIR__, 2).'/app/Support/IndicadoresDeDesempeno/IndicadoresDeDesempenoChartTabs.php';

    expect(file_exists($chartsTabsWidgetPath))->toBeTrue()
        ->and(file_exists($chartsTabsViewPath))->toBeTrue()
        ->and(file_exists($chartTabsPath))->toBeTrue();

    expect(file_get_contents($chartsTabsWidgetPath))->toContain('IndicadoresDeDesempenoChartTabs::definitions()')
        ->toContain("protected string \$view = 'filament.operations.indicadores-de-desempeno-charts-tabs';");

    expect(file_get_contents($chartsTabsViewPath))->toContain('fi-supplier-status-tabs-ios')
        ->toContain('@livewire($activeChartWidgetClass')
        ->toContain('wire:click="$set(\'activeChartTab\'');

    expect(file_get_contents($chartTabsPath))->toContain('ColaboradoresHelpdeskTicketsChart::class')
        ->toContain('SupplierObservationsChart::class')
        ->toContain('SupplierProviderSystemUpdateChart::class')
        ->toContain('SupplierNewProviderCreationChart::class')
        ->toContain('SupplierAcceptanceLettersChart::class');

    $supplierWidgetPath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/IndicadoresDeDesempeno/Widgets/SupplierObservationsChart.php';
    $supplierSeriesPath = dirname(__DIR__, 2).'/app/Support/IndicadoresDeDesempeno/SupplierObservationsChartSeries.php';

    expect(file_exists($supplierWidgetPath))->toBeTrue()
        ->and(file_exists($supplierSeriesPath))->toBeTrue()
        ->and(file_exists(dirname(__DIR__, 2).'/resources/views/filament/widgets/supplier-observations-chart.blade.php'))->toBeFalse();

    expect(file_get_contents($supplierWidgetPath))->toContain('SupplierObservationsChartSeries::groupedByCollaborator')
        ->toContain('SupplierObservationsChartSeries::LABEL_JURIDICOS')
        ->toContain('SupplierObservationsChartSeries::LABEL_NATURALES')
        ->not->toContain('openSupplierTypeDetail')
        ->not->toContain('resetToOverview');

    expect(file_get_contents($supplierSeriesPath))->toContain('SupplierObservacion::query()')
        ->toContain('DoctorNurseObservacion::query()')
        ->toContain('groupedByCollaborator')
        ->toContain('applyCollaboratorFilter')
        ->toContain("NULLIF(TRIM(created_by), '') IS NOT NULL");

    expect($widgetContents)->toContain('ColaboradoresHelpdeskTicketsChartSeries::totalsByColaborador')
        ->toContain("protected ?string \$heading = 'Tickets creados por colaborador';")
        ->toContain("protected string \$view = 'filament.operations.indicadores-de-desempeno-chart';")
        ->toContain('protected ?string $maxHeight');

    expect(file_exists(dirname(__DIR__, 2).'/resources/views/filament/operations/indicadores-de-desempeno-chart.blade.php'))->toBeTrue();

    $appServiceProviderContents = file_get_contents($appServiceProviderPath);

    expect($panelProviderContents)->not->toContain("app_path('Filament/Operations/IndicadoresDeDesempeno/Pages')")
        ->toContain("app_path('Filament/Operations/Resources')");

    expect($appServiceProviderContents)->toContain("Livewire::component('app.filament.operations.resources.indicadores-de-desempeno.widgets.colaborador-activities-speedometer-widget', ColaboradorActivitiesSpeedometerWidget::class);")
        ->toContain("Livewire::component('app.filament.operations.resources.indicadores-de-desempeno.widgets.indicadores-de-desempeno-charts-tabs-widget', IndicadoresDeDesempenoChartsTabsWidget::class);")
        ->toContain("Livewire::component('app.filament.operations.resources.indicadores-de-desempeno.widgets.colaboradores-helpdesk-tickets-chart', ColaboradoresHelpdeskTicketsChart::class);")
        ->toContain("Livewire::component('app.filament.operations.resources.indicadores-de-desempeno.widgets.supplier-observations-chart', SupplierObservationsChart::class);")
        ->toContain("Livewire::component('app.filament.operations.resources.indicadores-de-desempeno.widgets.supplier-provider-system-update-chart', SupplierProviderSystemUpdateChart::class);")
        ->toContain("Livewire::component('app.filament.operations.resources.indicadores-de-desempeno.widgets.supplier-new-provider-creation-chart', SupplierNewProviderCreationChart::class);")
        ->toContain("Livewire::component('app.filament.operations.resources.indicadores-de-desempeno.widgets.supplier-acceptance-letters-chart', SupplierAcceptanceLettersChart::class);");

    expect(file_get_contents($calendariosTdgPath))->toContain('protected static ?int $navigationSort = 3;');

    $corporateAllyPath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/CorporateAllies/CorporateAllyResource.php';
    expect(file_get_contents($corporateAllyPath))->toContain('protected static ?int $navigationSort = 5;');
});

it('usa la misma lista de responsables que el gráfico de observaciones de proveedores', function () {
    $helpdeskSeriesPath = dirname(__DIR__, 2).'/app/Support/IndicadoresDeDesempeno/ColaboradoresHelpdeskTicketsChartSeries.php';

    expect(file_get_contents($helpdeskSeriesPath))->toContain('SupplierObservationsChartSeries::collaboratorLabels')
        ->toContain("HelpDesk::query()->where('created_by', \$collaboratorName)")
        ->not->toContain('COLABORADOR_IDS')
        ->not->toContain('RrhhColaborador::query()');
});

it('agrupa observaciones jurídicas y naturales por colaborador en un gráfico doble', function () {
    expect(SupplierObservationsChartSeries::LABEL_JURIDICOS)->toBe('Proveedores jurídicos')
        ->and(SupplierObservationsChartSeries::LABEL_NATURALES)->toBe('Proveedores naturales');

    $seriesPath = dirname(__DIR__, 2).'/app/Support/IndicadoresDeDesempeno/SupplierObservationsChartSeries.php';
    $seriesContents = file_get_contents($seriesPath);

    expect($seriesContents)->toContain("'labels' => \$collaborators")
        ->toContain("'juridicos' => \$juridicosData")
        ->toContain("'naturales' => \$naturalesData")
        ->toContain('TYPE_NATURALES => DoctorNurseObservacion::query()')
        ->not->toContain('Sin colaborador');
});

it('agrupa actualizaciones de proveedores jurídicos y naturales por colaborador', function () {
    $widgetPath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/IndicadoresDeDesempeno/Widgets/SupplierProviderSystemUpdateChart.php';
    $seriesPath = dirname(__DIR__, 2).'/app/Support/IndicadoresDeDesempeno/SupplierProviderSystemUpdateChartSeries.php';

    expect(file_exists($widgetPath))->toBeTrue()
        ->and(file_exists($seriesPath))->toBeTrue();

    expect(file_get_contents($widgetPath))->toContain('SupplierProviderSystemUpdateChartSeries::groupedByCollaborator')
        ->toContain("protected ?string \$heading = 'Actualización del proveedor en el sistema';");

    $seriesContents = file_get_contents($seriesPath);

    expect($seriesContents)->toContain('AUDIT_OPERATIONS_SUPPLIER_UPDATED')
        ->toContain('AUDIT_OPERATIONS_SUPPLIER_DOCUMENT_UPLOADED')
        ->toContain('AUDIT_OPERATIONS_DOCTOR_NURSE_UPDATED')
        ->toContain('AUDIT_OPERATIONS_DOCTOR_NURSE_DOCUMENT_UPLOADED')
        ->toContain('SupplierContactPrincipal::query()')
        ->toContain('SupplierZonaCobertura::query()')
        ->toContain('DOCTOR_NURSE_RELEVANT_FIELDS')
        ->toContain('SUPPLIER_RELEVANT_FIELDS')
        ->toContain('countsFromAuditLogs')
        ->not->toContain('DoctorNurse::query()')
        ->toContain("'juridicos' => \$juridicosData")
        ->toContain("'naturales' => \$naturalesData");

    expect(SupplierProviderSystemUpdateChartSeries::LABEL_JURIDICOS)->toBe('Proveedores jurídicos')
        ->and(SupplierProviderSystemUpdateChartSeries::LABEL_NATURALES)->toBe('Proveedores naturales');
});

it('agrupa creaciones de proveedores jurídicos y naturales con correo por colaborador', function () {
    $widgetPath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/IndicadoresDeDesempeno/Widgets/SupplierNewProviderCreationChart.php';
    $seriesPath = dirname(__DIR__, 2).'/app/Support/IndicadoresDeDesempeno/SupplierNewProviderCreationChartSeries.php';

    expect(file_exists($widgetPath))->toBeTrue()
        ->and(file_exists($seriesPath))->toBeTrue();

    expect(file_get_contents($widgetPath))->toContain('SupplierNewProviderCreationChartSeries::groupedByCollaborator')
        ->toContain("protected ?string \$heading = 'Creación de un nuevo proveedor';")
        ->toContain('envío de kit por correo');

    $seriesContents = file_get_contents($seriesPath);

    expect($seriesContents)->toContain('Supplier::query()')
        ->toContain('DoctorNurse::query()')
        ->toContain('applyEmailFilter')
        ->toContain('correo_principal')
        ->toContain('applyCollaboratorFilter')
        ->toContain("'juridicos' => \$juridicosData")
        ->toContain("'naturales' => \$naturalesData");

    expect(SupplierNewProviderCreationChartSeries::LABEL_JURIDICOS)->toBe('Proveedores jurídicos')
        ->and(SupplierNewProviderCreationChartSeries::LABEL_NATURALES)->toBe('Proveedores naturales');
});

it('agrupa cartas de aceptación logradas por colaborador en jurídicos y naturales', function () {
    $widgetPath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/IndicadoresDeDesempeno/Widgets/SupplierAcceptanceLettersChart.php';
    $seriesPath = dirname(__DIR__, 2).'/app/Support/IndicadoresDeDesempeno/SupplierAcceptanceLettersChartSeries.php';

    expect(file_exists($widgetPath))->toBeTrue()
        ->and(file_exists($seriesPath))->toBeTrue();

    expect(file_get_contents($widgetPath))->toContain('SupplierAcceptanceLettersChartSeries::groupedByCollaborator')
        ->toContain("protected ?string \$heading = 'Cartas de aceptación logradas';")
        ->toContain('carta-acceptance.upload');

    $seriesContents = file_get_contents($seriesPath);

    expect($seriesContents)->toContain('AUDIT_OPERATIONS_SUPPLIER_DOCUMENT_UPLOADED')
        ->toContain('AUDIT_OPERATIONS_DOCTOR_NURSE_DOCUMENT_UPLOADED')
        ->toContain('CARTA_ACEPTACION')
        ->toContain('carta-acceptance.upload')
        ->toContain('isCartaAcceptanceUpload')
        ->toContain('resolveCollaboratorName')
        ->toContain("'juridicos' => \$juridicosData")
        ->toContain("'naturales' => \$naturalesData");

    expect(SupplierAcceptanceLettersChartSeries::LABEL_JURIDICOS)->toBe('Proveedores jurídicos')
        ->and(SupplierAcceptanceLettersChartSeries::LABEL_NATURALES)->toBe('Proveedores naturales');
});
