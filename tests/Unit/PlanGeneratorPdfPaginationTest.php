<?php

declare(strict_types=1);

use App\Models\PlanGenerator;
use App\Support\PlanGenerators\PlanGeneratorPdfPagination;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

uses(Tests\TestCase::class);

/**
 * @param  list<string>  $headers
 * @return list<array{column_key: string, header_label: string}>
 */
function columnasDePlanPdf(array $headers): array
{
    $columns = [];

    foreach ($headers as $index => $header) {
        $columns[] = [
            'column_key' => 'col-'.$index,
            'header_label' => $header,
        ];
    }

    return $columns;
}

/**
 * @param  list<string>  $labels
 * @param  list<array{column_key: string, header_label: string}>  $columns
 * @return array<string, array<string, mixed>>
 */
function filasDeBeneficiosPdf(array $labels, array $columns, bool $ultimoConMonto = true): array
{
    $rows = [];
    $lastIndex = count($labels) - 1;

    foreach ($labels as $index => $label) {
        $cells = [];

        foreach ($columns as $column) {
            $cells[$column['column_key']] = [
                'is_selected' => true,
                'coverage_amount' => $ultimoConMonto && $index === $lastIndex ? 5000 * ($index + 1) : null,
            ];
        }

        $rows['row-'.$index] = [
            'benefit_label' => $label,
            'cells' => $cells,
        ];
    }

    return $rows;
}

/**
 * @param  list<array{column_key: string, header_label: string}>  $columns
 * @param  list<array{label: string, population: int|null, rate: float}>  $definitions
 * @return array<string, array<string, mixed>>
 */
function filasDeTarifaPdf(array $columns, array $definitions): array
{
    $rows = [];

    foreach ($definitions as $index => $definition) {
        $cells = [];

        foreach ($columns as $columnIndex => $column) {
            $cells[$column['column_key']] = [
                'rate_amount' => $definition['rate'] + ($columnIndex * 25),
            ];
        }

        $rows['rate-'.$index] = [
            'age_range_label' => $definition['label'],
            'population' => $definition['population'],
            'cells' => $cells,
        ];
    }

    return $rows;
}

/**
 * @return list<string>
 */
function beneficiosLargosCapemiac(): array
{
    return [
        'ATENCION MÉDICA TELEFONICA (TELEMEDICINA)',
        'ENTREGA DE TRATAMIENTO MÉDICO A DOMICILIO',
        'MONITOREO TELEFÓNICO EVOLUTIVO',
        'ATENCIÓN MÉDICA DOMICILIARIA CON TRATAMIENTO DE UNIDOSIS INCLUIDA',
        'LABORATORIOS A DOMICILIO CON FINES DIAGNÓSTICOS',
        'IMAGENOLOGÍA A DOMICILIO CON FINES DIAGNÓSTICOS',
        'SEGUIMIENTO E INTERPRETACIÓN DE RESULTADOS',
        'TRASLADOS EN AMBULANCIA HACIA UN CENTRO HOSPITALARIO DERIVADO DE AMD',
        'CONSULTA ONLINE O PRESENCIAL CON MÉDICOS ESPECIALISTAS',
        'URGENCIAS MENORES EN DOMICILIO',
        'EMERGENCIAS MÉDICAS POR PATOLOGIAS LISTADAS',
    ];
}

/**
 * @param  list<array{column_key: string, header_label: string}>  $columns
 * @param  array<string, array<string, mixed>>  $rows
 * @param  array<string, array<string, mixed>>  $rateRows
 */
function htmlDePlanPdf(array $columns, array $rows, array $rateRows, string $conditions = ''): string
{
    $plan = new PlanGenerator([
        'name' => 'PLAN ESPECIAL CAPEMIAC',
        'control_number' => '0002-1',
        'client_data' => 'RUTACA',
        'agent_name' => 'MARIA TERESA BAUTISTA',
        'population_summary' => '800',
        'population_unit' => 'poblacion',
        'include_monthly_total' => false,
        'brand_color' => '#1d4ed8',
    ]);
    $plan->id = 23;
    $plan->issued_at = Carbon::parse('2026-09-23');
    $plan->conditions = $conditions;

    return view('documents.plan-generator-preview', [
        'planGenerator' => $plan,
        'columns' => $columns,
        'rows' => $rows,
        'rateRows' => $rateRows,
        'logoDataUri' => '',
        'generatedAt' => Carbon::parse('2026-09-23 09:29:00'),
        'brandColor' => '#1d4ed8',
        'brandColorBorder' => '#1e40af',
        'useQuotationBody' => false,
        'quotationPages' => [],
    ])->render();
}

/**
 * @param  list<array{column_key: string, header_label: string}>  $columns
 * @param  array<string, array<string, mixed>>  $rows
 * @param  array<string, array<string, mixed>>  $rateRows
 */
function pdfDePlan(array $columns, array $rows, array $rateRows): string
{
    $binary = Pdf::loadHTML(htmlDePlanPdf($columns, $rows, $rateRows))
        ->setPaper('a4', 'portrait')
        ->setOptions([
            'isRemoteEnabled' => false,
            'isJavascriptEnabled' => false,
            'isPhpEnabled' => false,
            'dpi' => 72,
            'isFontSubsettingEnabled' => false,
            'defaultFont' => 'DejaVu Sans',
        ], mergeWithDefaults: true)
        ->output();

    return $binary;
}

it('manda los cálculos a la hoja siguiente cuando los beneficios no dejan sitio', function (): void {
    $columns = columnasDePlanPdf([
        'PLAN ESPECIAL 5K',
        'PLAN ESPECIAL 10K',
        'PLAN ESPECIAL 20K',
        'PLAN ESPECIAL 30K',
        'PLAN ESPECIAL 40K',
        'PLAN ESPECIAL 50K',
    ]);
    $rows = filasDeBeneficiosPdf(beneficiosLargosCapemiac(), $columns);
    $rateRows = filasDeTarifaPdf($columns, [
        ['label' => '0 a 30 años', 'population' => 800, 'rate' => 265],
        ['label' => '31 a 65 años', 'population' => null, 'rate' => 290],
        ['label' => '66 a 74 años', 'population' => null, 'rate' => 482],
        ['label' => '75 a 85 años', 'population' => null, 'rate' => 605],
    ]);

    expect(PlanGeneratorPdfPagination::calculationsStartOnNextPage($columns, $rows, $rateRows, false, 'Población'))
        ->toBeTrue();

    $html = htmlDePlanPdf($columns, $rows, $rateRows);

    expect($html)
        ->toContain('pdf-plan-calc-keep pdf-plan-calc-next-page')
        ->toContain('pdf-plan-calc-keep')
        ->and(strpos($html, 'pdf-benefits-table'))->toBeLessThan(strpos($html, 'pdf-plan-calc-keep'))
        ->and(strpos($html, 'pdf-plan-calc-keep'))->toBeLessThan(strpos($html, 'Tarifa individual anual'))
        ->and(strpos($html, 'Tarifa individual anual'))->toBeLessThan(strpos($html, 'Total grupal'));
});

it('deja los cálculos en la misma hoja cuando la matriz de beneficios es corta', function (): void {
    $columns = columnasDePlanPdf(['PLAN ESPECIAL 5K', 'PLAN ESPECIAL 10K']);
    $rows = filasDeBeneficiosPdf([
        'TELEMEDICINA',
        'LABORATORIO',
        'AMBULANCIA',
    ], $columns, false);
    $rateRows = filasDeTarifaPdf($columns, [
        ['label' => '0 a 30 años', 'population' => 10, 'rate' => 100],
        ['label' => '31 a 65 años', 'population' => 4, 'rate' => 180],
    ]);

    expect(PlanGeneratorPdfPagination::calculationsStartOnNextPage($columns, $rows, $rateRows, false, 'Población'))
        ->toBeFalse();

    expect(htmlDePlanPdf($columns, $rows, $rateRows))
        ->toContain('pdf-plan-calc-keep')
        ->not->toContain('pdf-plan-calc-keep pdf-plan-calc-next-page');
});

it('imprime las condiciones debajo del total grupal y las cuenta para el salto de página', function (): void {
    $columns = columnasDePlanPdf(['PLAN ESPECIAL 5K', 'PLAN ESPECIAL 10K']);
    $rows = filasDeBeneficiosPdf(['TELEMEDICINA', 'LABORATORIO', 'AMBULANCIA'], $columns, false);
    $rateRows = filasDeTarifaPdf($columns, [
        ['label' => '0 a 30 años', 'population' => 10, 'rate' => 100],
        ['label' => '31 a 65 años', 'population' => 4, 'rate' => 180],
    ]);

    $html = htmlDePlanPdf(
        $columns,
        $rows,
        $rateRows,
        "* Cotización válida por 15 días.\n* Las tarifas no incluyen IVA.",
    );

    expect(strpos($html, 'Total grupal'))->toBeLessThan(strpos($html, 'Condiciones'))
        ->and(strpos($html, 'Condiciones'))->toBeLessThan(strpos($html, '* Cotización válida por 15 días.'))
        ->and(strpos($html, '* Cotización válida por 15 días.'))->toBeLessThan(strpos($html, '* Las tarifas no incluyen IVA.'))
        ->and($html)->toContain('white-space: pre-wrap');

    expect(PlanGeneratorPdfPagination::calculationsStartOnNextPage($columns, $rows, $rateRows, false, 'Población'))
        ->toBeFalse()
        ->and(PlanGeneratorPdfPagination::calculationsStartOnNextPage(
            $columns,
            $rows,
            $rateRows,
            false,
            'Población',
            str_repeat("Condición comercial de la cotización derivada.\n", 80),
        ))->toBeTrue();
});

it('no parte el título del total grupal de su tabla en el pdf', function (): void {
    $preview = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/plan-generator-preview.blade.php');
    $body = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/partials/plan-generator-plan-body.blade.php');

    expect($preview)
        ->toContain('page-break-inside: avoid')
        ->toContain('pdf-plan-calc-next-page')
        ->toContain('page-break-before: always')
        ->toContain('table.matrix-table.pdf-benefits-table th')
        ->toContain('font-size: 5.5pt')
        ->toContain('pdf-benefits-table');

    expect($body)
        ->toContain('pdf-plan-calc-keep')
        ->toContain('calculationsStartOnNextPage')
        ->toContain('pdf-benefits-table');

    $columns = columnasDePlanPdf([
        'PLAN ESPECIAL 5K',
        'PLAN ESPECIAL 10K',
        'PLAN ESPECIAL 20K',
        'PLAN ESPECIAL 30K',
        'PLAN ESPECIAL 40K',
        'PLAN ESPECIAL 50K',
    ]);
    $rows = filasDeBeneficiosPdf(beneficiosLargosCapemiac(), $columns);
    $rateRows = filasDeTarifaPdf($columns, [
        ['label' => '0 a 30 años', 'population' => 800, 'rate' => 265],
        ['label' => '31 a 65 años', 'population' => null, 'rate' => 290],
        ['label' => '66 a 74 años', 'population' => null, 'rate' => 482],
        ['label' => '75 a 85 años', 'population' => null, 'rate' => 605],
    ]);

    $largePath = sys_get_temp_dir().'/plan-pdf-beneficios-largos.pdf';
    file_put_contents($largePath, pdfDePlan($columns, $rows, $rateRows));

    $shortColumns = columnasDePlanPdf(['PLAN ESPECIAL 5K', 'PLAN ESPECIAL 10K']);
    $shortPath = sys_get_temp_dir().'/plan-pdf-beneficios-cortos.pdf';
    file_put_contents($shortPath, pdfDePlan(
        $shortColumns,
        filasDeBeneficiosPdf(['TELEMEDICINA', 'LABORATORIO', 'AMBULANCIA'], $shortColumns, false),
        filasDeTarifaPdf($shortColumns, [
            ['label' => '0 a 30 años', 'population' => 10, 'rate' => 100],
            ['label' => '31 a 65 años', 'population' => 4, 'rate' => 180],
        ]),
    ));

    expect(paginasDelPdf($largePath))->toBe(2)
        ->and(paginasDelPdf($shortPath))->toBe(1);
});

function paginasDelPdf(string $path): int
{
    $raw = (string) file_get_contents($path);

    return preg_match_all('/\/Type\s*\/Page[^s]/', $raw) ?: 0;
}
