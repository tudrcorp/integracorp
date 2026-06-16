<?php

declare(strict_types=1);

it('recurso generador de planes expone menu y restriccion superadmin', function (): void {
    $resource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/PlanGenerators/PlanGeneratorResource.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/PlanGenerators/Tables/PlanGeneratorsTable.php');

    expect($resource)
        ->toContain('Generador de Planes')
        ->toContain('CONFIGURACIÓN')
        ->toContain('SUPERADMIN');

    expect($table)
        ->toContain('Generador de planes')
        ->toContain('ColumnGroup::make')
        ->toContain('control_number')
        ->toContain('SelectFilter::make(\'status\')');
});

it('formulario generador incluye matrices alineadas con columnas compartidas', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/PlanGenerators/Schemas/PlanGeneratorForm.php');
    $stacked = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/plan-generators/stacked-matrices-editor.blade.php');
    $trait = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/PlanGenerators/Pages/Concerns/InteractsWithPlanGeneratorMatrix.php');

    expect($form)
        ->toContain('Tabs::make(\'planGeneratorFormTabs\')')
        ->toContain('TABS_CONTAINER')
        ->toContain('persistTab()')
        ->toContain('Cuerpo de la cotización')
        ->toContain('quotation_page_count')
        ->toContain('plan_page_number')
        ->toContain('quotation_pages')
        ->toContain('plan-generator-quotation')
        ->toContain('Agregar columna')
        ->toContain('stacked-matrices-editor')
        ->toContain('Matrices del plan')
        ->toContain('Propuesta comercial')
        ->toContain('control_number')
        ->toContain('client_data')
        ->toContain('issued_at')
        ->toContain('agent_name')
        ->toContain('population_summary')
        ->toContain('Hidden::make(\'rows\')')
        ->toContain('Hidden::make(\'rate_rows\')')
        ->toContain('normalizeColumns');

    expect($stacked)
        ->toContain('Beneficios del Plan')
        ->toContain('Tarifa individual Anual')
        ->toContain('Población')
        ->toContain('pg-stacked-editor')
        ->toContain('addMatrixRow')
        ->toContain('addRateRow');

    expect($trait)->toContain('matrixFormStateForPersistence');
});

it('estado de matriz inicializa celdas por columna', function (): void {
    $rows = App\Support\PlanGenerators\PlanGeneratorMatrixState::ensureRowsHaveCells([
        'row-1' => ['benefit_label' => 'Test'],
    ], [
        ['column_key' => 'col-a', 'header_label' => 'Ideal 5K'],
    ]);

    expect($rows['row-1']['cells']['col-a']['is_selected'])->toBeFalse()
        ->and($rows['row-1']['cells']['col-a']['coverage_amount'])->toBeNull();
});

it('infolist y vista muestran matrices alineadas del plan generado', function (): void {
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/PlanGenerators/Schemas/PlanGeneratorInfolist.php');
    $preview = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/plan-generators/stacked-matrices-preview.blade.php');
    $viewPage = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/PlanGenerators/Pages/ViewPlanGenerator.php');
    $resource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/PlanGenerators/PlanGeneratorResource.php');
    $pdf = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/plan-generator-preview.blade.php');
    $pdfBody = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/partials/plan-generator-plan-body.blade.php');

    expect($infolist)
        ->toContain('Tabs::make(\'planGeneratorInfolistTabs\')')
        ->toContain('TABS_CONTAINER')
        ->toContain('persistTab()')
        ->toContain('Cuerpo de la cotización')
        ->toContain('stacked-matrices-preview')
        ->toContain('plan-pdf-trigger')
        ->toContain('PlanGeneratorPreviewBuilder')
        ->toContain('Propuesta comercial')
        ->toContain('control_number')
        ->toContain('population_summary');

    expect($preview)
        ->toContain('Beneficios del Plan')
        ->toContain('Tarifa individual Anual')
        ->toContain('pg-col-plan');

    expect($viewPage)
        ->toContain('planPdfPreview')
        ->toContain('plan-pdf-panel');

    expect($resource)
        ->toContain('ViewPlanGenerator')
        ->toContain('PlanGeneratorInfolist')
        ->toContain('canView');

    expect($pdf)->toContain('useQuotationBody')
        ->toContain('pdf-image-frame')
        ->toContain('A4 portrait')
        ->toContain('pdf-plan-margin-cell')
        ->toContain('padding: 20mm')
        ->toContain('plan-generator-plan-body');

    expect($pdfBody)->toContain('pg-col-plan')
        ->toContain('Propuesta Comercial')
        ->toContain('Nro. Control:')
        ->toContain('Datos del cliente:')
        ->toContain('Población:');
});

it('cuerpo de cotizacion sincroniza paginas y valida imagenes', function (): void {
    $synced = App\Support\PlanGenerators\PlanGeneratorQuotationState::syncImagePagesForQuotation([
        ['page_number' => 1, 'image' => 'a.jpg'],
        ['page_number' => 2, 'image' => 'b.jpg'],
        ['page_number' => 4, 'image' => 'c.jpg'],
    ], 4, 3);

    expect($synced)->toHaveCount(3)
        ->and(array_column($synced, 'page_number'))->toBe([1, 2, 4])
        ->and($synced[0]['image'])->toBe('a.jpg')
        ->and($synced[1]['image'])->toBe('b.jpg')
        ->and($synced[2]['image'])->toBe('c.jpg');

    expect(App\Support\PlanGenerators\PlanGeneratorQuotationValidator::validationMessage(2, 1, [
        ['page_number' => 2, 'image' => null],
    ]))->toContain('debe incluir una imagen');

    expect(App\Support\PlanGenerators\PlanGeneratorQuotationValidator::validationMessage(3, 2, [
        ['page_number' => 1, 'image' => 'a.jpg'],
        ['page_number' => 3, 'image' => 'b.jpg'],
    ]))->toBeNull();

    expect(App\Support\PlanGenerators\PlanGeneratorQuotationState::extractImagePath([
        'plan-generator-quotation/factura.jpg' => 'plan-generator-quotation/factura.jpg',
    ]))->toBe('plan-generator-quotation/factura.jpg');

    expect(App\Support\PlanGenerators\PlanGeneratorQuotationValidator::validationMessage(3, 2, [
        ['page_number' => 1, 'image' => ['plan-generator-quotation/a.jpg' => 'plan-generator-quotation/a.jpg']],
        ['page_number' => 3, 'image' => ['plan-generator-quotation/b.jpg']],
    ]))->toBeNull();

    expect(App\Support\PlanGenerators\PlanGeneratorQuotationValidator::validationMessage(2, null, [
        ['page_number' => 1, 'image' => 'a.jpg'],
        ['page_number' => 2, 'image' => 'b.jpg'],
    ]))->toContain('Indique en qué página');
});

it('preview builder formatea montos y rutas pdf del plan generado', function (): void {
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/BusinessPlanGeneratorPdfController.php');

    expect(App\Support\PlanGenerators\PlanGeneratorPreviewBuilder::formatCoverageAmount(5000.0))->toBe('5,000.00')
        ->and(App\Support\PlanGenerators\PlanGeneratorPreviewBuilder::formatCoverageAmount(null))->toBe('')
        ->and(App\Support\PlanGenerators\PlanGeneratorPreviewBuilder::formatRateAmount(265.0))->toBe('265')
        ->and(App\Support\PlanGenerators\PlanGeneratorPreviewBuilder::formatRateAmount(1068.0))->toBe('1.068');

    expect($routes)
        ->toContain('business.plan-generators.pdf.preview')
        ->toContain('business.plan-generators.pdf.download')
        ->toContain('BusinessPlanGeneratorPdfController');

    expect($controller)
        ->toContain('PlanGeneratorPdfAccess')
        ->toContain('preview')
        ->toContain('download');
});

it('columnas del plan se normalizan y alinean celdas en el mismo orden', function (): void {
    $ordered = App\Support\PlanGenerators\PlanGeneratorMatrixState::orderBenefitCellsForColumns([
        'col-b' => ['is_selected' => true, 'coverage_amount' => 100],
        'col-a' => ['is_selected' => false, 'coverage_amount' => null],
    ], [
        ['column_key' => 'col-a', 'header_label' => 'AS1'],
        ['column_key' => 'col-b', 'header_label' => 'AS2'],
    ]);

    expect(array_keys($ordered))->toBe(['col-a', 'col-b'])
        ->and($ordered['col-b']['is_selected'])->toBeTrue();

    expect(App\Support\PlanGenerators\PlanGeneratorMatrixState::normalizeColumns([
        ['column_key' => 'x', 'header_label' => 'Plan A'],
        ['header_label' => 'Sin key'],
        ['column_key' => 'y', 'header_label' => 'Plan B'],
    ]))->toHaveCount(2);

    $rateRows = App\Support\PlanGenerators\PlanGeneratorMatrixState::ensureRateRowsHaveCells([
        'rate-1' => ['age_range_label' => '0 - 30', 'population' => 29],
    ], [
        ['column_key' => 'col-a', 'header_label' => 'Especial US$ 5K'],
    ]);

    expect($rateRows['rate-1']['cells']['col-a']['rate_amount'])->toBeNull();
});

it('validador de poblacion exige que el total coincida con la suma por rango etario', function (): void {
    $validator = App\Support\PlanGenerators\PlanGeneratorPopulationValidator::class;

    expect($validator::parsePopulationTotal('101 personas'))->toBe(101)
        ->and($validator::sumRateRowPopulations([
            'r1' => ['population' => 29],
            'r2' => ['population' => 72],
        ]))->toBe(101)
        ->and($validator::validationMessage('101 personas', [
            'r1' => ['population' => 29],
            'r2' => ['population' => 72],
        ]))->toBeNull()
        ->and($validator::validationMessage('101 personas', [
            'r1' => ['population' => 10],
        ]))->toContain('debe ser igual a la suma');
});

it('total grupal calcula anual semestral y trimestral por columna', function (): void {
    $columns = [
        ['column_key' => 'col-a', 'header_label' => 'Especial US$ 5K'],
        ['column_key' => 'col-b', 'header_label' => 'Especial US$ 10K'],
    ];

    $rateRows = [
        'r1' => [
            'age_range_label' => '0 - 30',
            'population' => 29,
            'cells' => [
                'col-a' => ['rate_amount' => 265],
                'col-b' => ['rate_amount' => 289],
            ],
        ],
        'r2' => [
            'age_range_label' => '31 - 65',
            'population' => 72,
            'cells' => [
                'col-a' => ['rate_amount' => 289],
                'col-b' => ['rate_amount' => 321],
            ],
        ],
    ];

    $totals = App\Support\PlanGenerators\PlanGeneratorGroupTotalCalculator::totalsByColumn($columns, $rateRows);

    expect($totals['annual']['col-a'])->toEqual(265 * 29 + 289 * 72)
        ->and($totals['semestral']['col-a'])->toEqual((265 * 29 + 289 * 72) / 2)
        ->and($totals['trimestral']['col-a'])->toEqual((265 * 29 + 289 * 72) / 4)
        ->and(App\Support\PlanGenerators\PlanGeneratorGroupTotalCalculator::formatGroupTotal(28493.0))->toBe('$28.493');

    $partial = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/plan-generators/partials/group-total-matrix.blade.php');
    expect($partial)
        ->toContain('Total Grupal')
        ->toContain('Tarifa anual')
        ->toContain('Tarifa Semestral')
        ->toContain('Tarifa Trimestral');

    $benefitStatus = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/plan-generators/partials/benefit-cell-status-preview.blade.php');
    expect($benefitStatus)
        ->not->toContain('Incluido')
        ->toContain('bg-rose-100')
        ->toContain('−');
});
