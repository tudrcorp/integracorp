<?php

declare(strict_types=1);

use App\Filament\Business\Resources\PlanGenerators\Pages\CreatePlanGenerator;
use App\Filament\Business\Resources\PlanGenerators\Pages\ListPlanGenerators;
use App\Models\PlanGenerator;
use App\Models\User;
use App\Services\PlanGeneratorPdfService;
use App\Support\PlanGenerators\PlanGeneratorPersistence;
use App\Support\PlanGenerators\PlanGeneratorPreviewBuilder;
use App\Support\PlanGenerators\PlanGeneratorRateAdjustment;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Ajuste global de tarifas: un porcentaje interno de aumento o descuento que el
 * cliente nunca ve, solo sus efectos sobre la tarifa.
 *
 * Todo lo que escribe va dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
});

afterEach(function (): void {
    DB::rollBack();
});

/**
 * @return array{columns: list<array<string, mixed>>, rate_rows: array<string, mixed>}
 */
function matrizDeTarifas(): array
{
    return [
        'columns' => [
            ['column_key' => 'col-a', 'header_label' => 'PLAN I 5K'],
            ['column_key' => 'col-b', 'header_label' => 'PLAN II 10K'],
        ],
        'rate_rows' => [
            'r1' => [
                'age_range_label' => '00 - 45',
                'population' => 1,
                'cells' => [
                    'col-a' => ['rate_amount' => 120.0, 'base_rate_amount' => null],
                    'col-b' => ['rate_amount' => 180.0, 'base_rate_amount' => null],
                ],
            ],
            'r2' => [
                'age_range_label' => '46 - 74',
                'population' => 1,
                'cells' => [
                    'col-a' => ['rate_amount' => 144.0, 'base_rate_amount' => null],
                    'col-b' => ['rate_amount' => 216.0, 'base_rate_amount' => null],
                ],
            ],
        ],
    ];
}

function tarifa(array $resultado, string $filaKey, string $columnaKey): ?float
{
    $amount = $resultado['rate_rows'][$filaKey]['cells'][$columnaKey]['rate_amount'] ?? null;

    return $amount === null ? null : (float) $amount;
}

it('aplica el descuento y redondea al entero más cercano', function (): void {
    $matriz = matrizDeTarifas();

    $resultado = PlanGeneratorRateAdjustment::apply(
        $matriz['columns'],
        $matriz['rate_rows'],
        -10,
        ['col-a', 'col-b'],
    );

    expect(tarifa($resultado, 'r1', 'col-a'))->toBe(108.0)
        ->and(tarifa($resultado, 'r1', 'col-b'))->toBe(162.0)
        // 144 − 10 % = 129,60 → 130
        ->and(tarifa($resultado, 'r2', 'col-a'))->toBe(130.0)
        // 216 − 10 % = 194,40 → 194
        ->and(tarifa($resultado, 'r2', 'col-b'))->toBe(194.0)
        ->and($resultado['adjusted_columns'])->toBe(2)
        ->and($resultado['adjusted_cells'])->toBe(4);

    // La tarifa original queda congelada como base.
    expect($resultado['rate_rows']['r2']['cells']['col-a']['base_rate_amount'])->toBe(144.0)
        ->and($resultado['columns'][0]['rate_adjustment_percent'])->toBe(-10.0);
});

it('un segundo ajuste se calcula sobre la tarifa original, no se compone', function (): void {
    $matriz = matrizDeTarifas();

    $primero = PlanGeneratorRateAdjustment::apply($matriz['columns'], $matriz['rate_rows'], -10, ['col-a']);
    $segundo = PlanGeneratorRateAdjustment::apply($primero['columns'], $primero['rate_rows'], -15, ['col-a']);

    // 120 − 15 % = 102. Compuesto daría 91,80.
    expect(tarifa($segundo, 'r1', 'col-a'))->toBe(102.0)
        ->and($segundo['rate_rows']['r1']['cells']['col-a']['base_rate_amount'])->toBe(120.0);
});

it('un ajuste en cero restaura la tarifa original y borra el porcentaje', function (): void {
    $matriz = matrizDeTarifas();

    $conDescuento = PlanGeneratorRateAdjustment::apply($matriz['columns'], $matriz['rate_rows'], -10, ['col-a']);
    $restaurado = PlanGeneratorRateAdjustment::apply($conDescuento['columns'], $conDescuento['rate_rows'], 0, ['col-a']);

    expect(tarifa($restaurado, 'r1', 'col-a'))->toBe(120.0)
        ->and(tarifa($restaurado, 'r2', 'col-a'))->toBe(144.0)
        ->and($restaurado['columns'][0]['rate_adjustment_percent'])->toBeNull()
        ->and(PlanGeneratorRateAdjustment::summary($restaurado['columns']))->toBeNull();
});

it('solo toca las columnas marcadas', function (): void {
    $matriz = matrizDeTarifas();

    $resultado = PlanGeneratorRateAdjustment::apply($matriz['columns'], $matriz['rate_rows'], 25, ['col-b']);

    expect(tarifa($resultado, 'r1', 'col-a'))->toBe(120.0)
        ->and($resultado['rate_rows']['r1']['cells']['col-a']['base_rate_amount'])->toBeNull()
        ->and($resultado['columns'][0]['rate_adjustment_percent'])->toBeNull()
        ->and(tarifa($resultado, 'r1', 'col-b'))->toBe(225.0)
        ->and($resultado['columns'][1]['rate_adjustment_percent'])->toBe(25.0)
        ->and($resultado['adjusted_columns'])->toBe(1)
        ->and($resultado['adjusted_cells'])->toBe(2);
});

it('una tarifa editada a mano se convierte en la nueva base', function (): void {
    $matriz = matrizDeTarifas();

    $conDescuento = PlanGeneratorRateAdjustment::apply($matriz['columns'], $matriz['rate_rows'], -10, ['col-a']);

    // El analista teclea 200 encima de la tarifa ya descontada (108).
    $conDescuento['rate_rows']['r1']['cells']['col-a']['rate_amount'] = 200.0;

    $reaplicado = PlanGeneratorRateAdjustment::apply($conDescuento['columns'], $conDescuento['rate_rows'], -10, ['col-a']);

    // Manda lo tecleado: 200 − 10 % = 180, y la base pasa a ser 200.
    expect(tarifa($reaplicado, 'r1', 'col-a'))->toBe(180.0)
        ->and($reaplicado['rate_rows']['r1']['cells']['col-a']['base_rate_amount'])->toBe(200.0)
        // La celda que no se tocó sigue calculando desde su original.
        ->and(tarifa($reaplicado, 'r2', 'col-a'))->toBe(130.0)
        ->and($reaplicado['rate_rows']['r2']['cells']['col-a']['base_rate_amount'])->toBe(144.0);
});

it('no inventa monto en una celda sin tarifa', function (): void {
    $matriz = matrizDeTarifas();
    $matriz['rate_rows']['r1']['cells']['col-a']['rate_amount'] = null;

    $resultado = PlanGeneratorRateAdjustment::apply($matriz['columns'], $matriz['rate_rows'], -10, ['col-a']);

    expect(tarifa($resultado, 'r1', 'col-a'))->toBeNull()
        ->and($resultado['rate_rows']['r1']['cells']['col-a']['base_rate_amount'])->toBeNull()
        // La otra fila de la misma columna sí se ajusta.
        ->and(tarifa($resultado, 'r2', 'col-a'))->toBe(130.0)
        ->and($resultado['adjusted_cells'])->toBe(1);
});

it('acota el porcentaje a un rango razonable', function (): void {
    expect(PlanGeneratorRateAdjustment::clampPercent(-500))->toBe(PlanGeneratorRateAdjustment::MIN_PERCENT)
        ->and(PlanGeneratorRateAdjustment::clampPercent(99999))->toBe(PlanGeneratorRateAdjustment::MAX_PERCENT)
        // −100 % deja la tarifa en cero, no negativa.
        ->and(PlanGeneratorRateAdjustment::adjustedAmount(120.0, -100))->toBe(0.0);
});

it('resume el ajuste vigente para consumo interno', function (): void {
    $matriz = matrizDeTarifas();
    $resultado = PlanGeneratorRateAdjustment::apply($matriz['columns'], $matriz['rate_rows'], -7.5, ['col-a']);

    expect(PlanGeneratorRateAdjustment::summary($resultado['columns']))->toBe('PLAN I 5K −7,5%')
        ->and(PlanGeneratorRateAdjustment::formatPercent(10.0))->toBe('+10%')
        ->and(PlanGeneratorRateAdjustment::columnOptions($resultado['columns']))
        ->toBe([
            'col-a' => 'PLAN I 5K (hoy −7,5%)',
            'col-b' => 'PLAN II 10K',
        ]);
});

it('guarda y relee el porcentaje y la tarifa base', function (): void {
    $matriz = matrizDeTarifas();
    $ajustada = PlanGeneratorRateAdjustment::apply($matriz['columns'], $matriz['rate_rows'], -10, ['col-a']);

    $plan = PlanGenerator::query()->create([
        'name' => 'PLAN AJUSTE PEST',
        'control_number' => 'PEST-AJUSTE-'.uniqid(),
        'client_data' => 'CLIENTE AJUSTE',
        'issued_at' => now(),
        'agent_name' => 'PEST',
        'population_summary' => '2',
        'status' => 'PRE-APROBADO',
    ]);

    PlanGeneratorPersistence::syncFromFormState($plan, [
        'columns' => $ajustada['columns'],
        'rows' => [],
        'rate_rows' => $ajustada['rate_rows'],
        'quotation_pages' => [],
    ]);

    $estado = PlanGeneratorPersistence::formStateFromModel($plan->fresh());
    $primeraFila = array_values($estado['rate_rows'])[0];

    expect($estado['columns'][0]['rate_adjustment_percent'])->toBe(-10.0)
        ->and($estado['columns'][1]['rate_adjustment_percent'])->toBeNull()
        ->and((float) $primeraFila['cells']['col-a']['rate_amount'])->toBe(108.0)
        ->and((float) $primeraFila['cells']['col-a']['base_rate_amount'])->toBe(120.0);

    // Reabrir y reaplicar sigue calculando desde el original guardado.
    $reaplicado = PlanGeneratorRateAdjustment::apply($estado['columns'], $estado['rate_rows'], -15, ['col-a']);
    $filaReaplicada = array_values($reaplicado['rate_rows'])[0];

    expect((float) $filaReaplicada['cells']['col-a']['rate_amount'])->toBe(102.0);
});

it('el porcentaje interno no sale en el PDF de la cotización', function (): void {
    $matriz = matrizDeTarifas();
    $ajustada = PlanGeneratorRateAdjustment::apply($matriz['columns'], $matriz['rate_rows'], -10, ['col-a', 'col-b']);

    $plan = PlanGenerator::query()->create([
        'name' => 'PLAN PDF AJUSTE PEST',
        'control_number' => 'PEST-PDF-'.uniqid(),
        'client_data' => 'CLIENTE PDF AJUSTE',
        'issued_at' => now(),
        'agent_name' => 'PEST',
        'population_summary' => '2',
        'status' => 'PRE-APROBADO',
        'brand_color' => '#1d4ed8',
    ]);

    PlanGeneratorPersistence::syncFromFormState($plan, [
        'columns' => $ajustada['columns'],
        'rows' => [],
        'rate_rows' => $ajustada['rate_rows'],
        'quotation_pages' => [],
    ]);

    $plan = $plan->fresh();
    $plan->loadMissing(['quotationPages']);

    // Se renderiza la misma vista que arma el PDF, con los mismos datos que le
    // pasa el servicio: es el único camino que prueba que no se filtra.
    $html = view('documents.plan-generator-preview', [
        'planGenerator' => $plan,
        ...(function (PlanGenerator $plan): array {
            $matrix = PlanGeneratorPreviewBuilder::fullMatrixFromModel($plan);

            return [
                'columns' => $matrix['columns'],
                'rows' => $matrix['rows'],
                'rateRows' => $matrix['rate_rows'],
            ];
        })($plan),
        'logoDataUri' => '',
        'generatedAt' => now(),
        'brandColor' => '#1d4ed8',
        'brandColorBorder' => '#1e40af',
        'useQuotationBody' => false,
        'quotationPages' => PlanGeneratorPdfService::quotationPagesForPdf($plan),
    ])->render();

    // La tarifa ajustada sí sale; el porcentaje y la base no aparecen por
    // ninguna vía.
    expect($html)
        ->toContain('108')
        ->not->toContain('rate_adjustment_percent')
        ->not->toContain('base_rate_amount')
        ->not->toContain('−10%')
        ->not->toContain('-10%')
        ->not->toContain('Ajuste interno');
});

it('la ficha del panel sí muestra el ajuste interno, la plantilla del PDF no', function (): void {
    $preview = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/plan-generators/stacked-matrices-preview.blade.php');
    $editor = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/plan-generators/stacked-matrices-editor.blade.php');
    $pdfBody = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/partials/plan-generator-plan-body.blade.php');
    $pdf = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/plan-generator-preview.blade.php');

    // Superficies internas del panel: avisan del ajuste.
    expect($preview)->toContain('Ajuste interno vigente')
        ->toContain('PlanGeneratorRateAdjustment::summary');

    expect($editor)->toContain('Ajuste interno vigente')
        ->toContain("getAction('adjustRateAmounts')");

    // Superficies que ve el cliente: ni el porcentaje ni la tarifa base.
    expect($pdfBody)
        ->not->toContain('rate_adjustment_percent')
        ->not->toContain('base_rate_amount')
        ->not->toContain('Ajuste interno');

    expect($pdf)
        ->not->toContain('rate_adjustment_percent')
        ->not->toContain('base_rate_amount')
        ->not->toContain('Ajuste interno');
});

it('el analista aplica el ajuste desde el formulario de crear plan', function (): void {
    Filament::setCurrentPanel('business');

    $analista = User::query()
        ->where('email', 'like', '%@tudrencasa.com')
        ->where('status', 'ACTIVO')
        ->get()
        ->first(fn (User $user): bool => in_array('NEGOCIOS', (array) ($user->departament ?? []), true)
            || in_array('SUPERADMIN', (array) ($user->departament ?? []), true));

    if ($analista === null) {
        $this->markTestSkipped('No hay un analista de Negocios activo en la base para montar el panel.');
    }

    $matriz = matrizDeTarifas();

    // El estado del modal se toca directo: el helper `callAction($accion, $data)`
    // rellena el formulario de la página, no el de la acción montada.
    $componente = Livewire::actingAs($analista)
        ->test(CreatePlanGenerator::class)
        ->set('data.columns', $matriz['columns'])
        ->set('data.rate_rows', $matriz['rate_rows'])
        ->mountAction(TestAction::make('adjustRateAmounts')->schemaComponent('planGeneratorMatrixEditor'));

    // El modal viene con todas las columnas marcadas; acá se deja solo una.
    $componente
        ->set('mountedActions.0.data.percent', -10)
        ->set('mountedActions.0.data.column_keys', ['col-a'])
        ->callMountedAction()
        ->assertHasNoErrors()
        ->assertNotified('Ajuste aplicado');

    $rateRows = $componente->get('data.rate_rows');
    $columns = $componente->get('data.columns');

    expect((float) $rateRows['r1']['cells']['col-a']['rate_amount'])->toBe(108.0)
        ->and((float) $rateRows['r1']['cells']['col-b']['rate_amount'])->toBe(180.0)
        ->and((float) $columns[0]['rate_adjustment_percent'])->toBe(-10.0);
});

it('el analista aplica el ajuste dentro de la modal de cotización derivada', function (): void {
    Filament::setCurrentPanel('business');

    $analista = User::query()
        ->where('email', 'like', '%@tudrencasa.com')
        ->where('status', 'ACTIVO')
        ->get()
        ->first(fn (User $user): bool => in_array('NEGOCIOS', (array) ($user->departament ?? []), true)
            || in_array('SUPERADMIN', (array) ($user->departament ?? []), true));

    if ($analista === null) {
        $this->markTestSkipped('No hay un analista de Negocios activo en la base para montar el panel.');
    }

    $plantilla = PlanGenerator::query()
        ->withCount(['columns', 'rateRows'])
        ->having('columns_count', '>=', 1)
        ->having('rate_rows_count', '>=', 1)
        ->first();

    if ($plantilla === null) {
        $this->markTestSkipped('No hay un plan generado con tarifas para derivar.');
    }

    $componente = Livewire::actingAs($analista)
        ->test(ListPlanGenerators::class)
        ->set('selectedTableRecords', [(string) $plantilla->getKey()])
        ->mountAction(TestAction::make('deriveQuotation')->table()->bulk());

    $estado = $componente->get('mountedActions.0.data');
    $columnaKey = (string) $estado['columns'][0]['column_key'];

    $tarifasPrevias = collect($estado['rate_rows'])
        ->mapWithKeys(fn (array $fila, string $key): array => [
            $key => PlanGeneratorRateAdjustment::parseAmount($fila['cells'][$columnaKey]['rate_amount'] ?? null),
        ])
        ->all();

    $componente
        ->mountAction(TestAction::make('adjustRateAmounts')->schemaComponent('derivedQuotationMatrixEditor'))
        ->set('mountedActions.1.data.percent', -10)
        ->set('mountedActions.1.data.column_keys', [$columnaKey])
        ->callMountedAction()
        ->assertHasNoErrors();

    $estadoAjustado = $componente->get('mountedActions.0.data');

    foreach ($tarifasPrevias as $filaKey => $tarifaPrevia) {
        $ajustada = PlanGeneratorRateAdjustment::parseAmount(
            $estadoAjustado['rate_rows'][$filaKey]['cells'][$columnaKey]['rate_amount'] ?? null,
        );

        expect($ajustada)->toBe(PlanGeneratorRateAdjustment::adjustedAmount($tarifaPrevia, -10));
    }

    expect(PlanGeneratorRateAdjustment::percentFor($estadoAjustado['columns'][0]))->toBe(-10.0);
});
