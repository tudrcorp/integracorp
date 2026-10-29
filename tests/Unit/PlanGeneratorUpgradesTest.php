<?php

declare(strict_types=1);

use App\Models\PlanGenerator;
use App\Models\PlanGeneratorUpgrade;
use App\Support\PlanGenerators\PlanGeneratorGroupTotalCalculator;
use App\Support\PlanGenerators\PlanGeneratorPdfPagination;
use App\Support\PlanGenerators\PlanGeneratorUpgrades;
use Illuminate\Support\Carbon;

uses(Tests\TestCase::class);

/**
 * Funerario: 0–64 = 30, 65–85 = 50, y sus servicios.
 *
 * @return array<string, mixed>
 */
function funerarioLine(array $overrides = []): array
{
    return array_replace([
        'line_key' => 'linea-funerario',
        'upgrade_id' => 7,
        'upgrade_coverage_id' => 70,
        'upgrade_code' => 'UPG-0007',
        'upgrade_name' => 'ASISTENCIA DE SERVICIOS FUNERARIOS',
        'coverage_amount' => 2000.0,
        'age_prices' => [
            ['id' => 701, 'age_from' => 0, 'age_to' => 64, 'price' => 30],
            ['id' => 702, 'age_from' => 65, 'age_to' => 85, 'price' => 50],
        ],
        'services' => [
            ['name' => 'Traslado funerario (Dentro de Venezuela)', 'description' => null],
            ['name' => 'Tanatopraxia', 'description' => null],
        ],
        'frozen' => true,
    ], $overrides);
}

/**
 * @return array<string, array<string, mixed>>
 */
function quoteRateRows(): array
{
    return [
        'a' => ['age_range_label' => 'Rango etario 0 - 30', 'population' => 40, 'cells' => ['c1' => ['rate_amount' => 311]]],
        'b' => ['age_range_label' => 'Rango etario 31 - 65', 'population' => 10, 'cells' => ['c1' => ['rate_amount' => 339]]],
        'c' => ['age_range_label' => 'Rango etario 66 - 74', 'population' => 0, 'cells' => ['c1' => ['rate_amount' => 564]]],
    ];
}

it('toma el rango del upgrade que contiene las edades y, si cruza dos, el precio más alto', function (): void {
    $prices = funerarioLine()['age_prices'];

    expect(PlanGeneratorUpgrades::priceForBounds($prices, 0, 30))->toBe(['price' => 30.0, 'range_id' => 701])
        ->and(PlanGeneratorUpgrades::priceForBounds($prices, 31, 65))->toBe(['price' => 50.0, 'range_id' => 702])
        ->and(PlanGeneratorUpgrades::priceForBounds($prices, 80, 90))->toBeNull()
        ->and(PlanGeneratorUpgrades::priceForLabel($prices, 'Rango etario 66 - 74'))->toBe(['price' => 50.0, 'range_id' => 702])
        ->and(PlanGeneratorUpgrades::priceForLabel($prices, 'Mayores de 65'))->toBeNull()
        ->and(PlanGeneratorUpgrades::priceForLabel($prices, 'Sin edades'))->toBeNull()
        ->and(PlanGeneratorUpgrades::priceForLabel($prices, '70 a 20'))->toBeNull();
});

it('cotiza cada upgrade por rango y población y suma el total anual', function (): void {
    $quote = PlanGeneratorUpgrades::quote([funerarioLine()], quoteRateRows());
    $upgrade = $quote['upgrades'][0];

    expect($quote['errors'])->toBe([])
        ->and($quote['population'])->toBe(50)
        ->and($upgrade['persons'])->toBe(50)
        ->and($upgrade['annual'])->toBe(1700.0)
        ->and($quote['annual_total'])->toBe(1700.0)
        ->and($upgrade['uniform_price'])->toBeNull()
        ->and(array_column($upgrade['rows'], 'subtotal'))->toBe([1200.0, 500.0, 0.0]);
});

it('con un solo precio para toda la población lo muestra como tarifa por persona', function (): void {
    $line = funerarioLine(['age_prices' => [['id' => 1, 'age_from' => 0, 'age_to' => 120, 'price' => 30]]]);
    $quote = PlanGeneratorUpgrades::quote([$line], quoteRateRows());

    expect($quote['upgrades'][0]['uniform_price'])->toBe(30.0)
        ->and($quote['annual_total'])->toBe(1500.0);
});

it('separa rangos cubiertos en parte de los que el upgrade no cubre en ninguna edad', function (): void {
    $rateRows = quoteRateRows();
    $rateRows['c']['population'] = 3;
    $rateRows['d'] = ['age_range_label' => 'Rango etario 80 - 90', 'population' => 2];
    $rateRows['e'] = ['age_range_label' => 'Rango etario 86 - 95', 'population' => 4];

    $quote = PlanGeneratorUpgrades::quote([funerarioLine()], $rateRows);
    $rows = $quote['upgrades'][0]['rows'];

    expect($quote['errors'])->toBe([])
        ->and($rows[3]['status'])->toBe(PlanGeneratorUpgrades::STATUS_PARTIAL)
        ->and($rows[3]['covered'])->toBe('80 a 85 años')
        ->and($rows[3]['price'])->toBe(50.0)
        ->and($rows[3]['charged_persons'])->toBe(0)
        ->and($rows[4]['status'])->toBe(PlanGeneratorUpgrades::STATUS_NO_PRICE)
        ->and($quote['upgrades'][0]['persons'])->toBe(53)
        ->and($quote['upgrades'][0]['covers'])->toBe('0 a 85 años')
        ->and($quote['annual_total'])->toBe(1850.0);
});

it('el analista saca rangos de la población del upgrade sin importar mayúsculas ni espacios', function (): void {
    $line = funerarioLine(['excluded_ranges' => ['  rango ETARIO 31 - 65 ']]);
    $quote = PlanGeneratorUpgrades::quote([$line], quoteRateRows());
    $upgrade = $quote['upgrades'][0];

    expect($upgrade['rows'][1]['status'])->toBe(PlanGeneratorUpgrades::STATUS_EXCLUDED)
        ->and($upgrade['persons'])->toBe(40)
        ->and($upgrade['annual'])->toBe(1200.0)
        ->and($upgrade['uniform_price'])->toBe(30.0)
        ->and(array_column(PlanGeneratorUpgrades::chargedRows($upgrade), 'label'))->toBe(['Rango etario 0 - 30'])
        ->and(PlanGeneratorUpgrades::perPersonForLabel([$line], 'Rango etario 31 - 65')['upgrades'])->toBe([])
        ->and(PlanGeneratorUpgrades::perPersonForLabel([$line], 'Rango etario 0 - 30')['total'])->toBe(30.0);
});

it('bloquea un upgrade que no se aplica a nadie', function (): void {
    $line = funerarioLine(['excluded_ranges' => ['Rango etario 0 - 30', 'Rango etario 31 - 65']]);

    expect(PlanGeneratorUpgrades::quote([$line], quoteRateRows())['errors'][0])->toContain('no se aplica a ninguna persona');
});

it('el precio por persona de un rango junta todos los upgrades para asignarlos', function (): void {
    $optica = funerarioLine(['upgrade_id' => 8, 'upgrade_coverage_id' => 80, 'upgrade_name' => 'ÓPTICA', 'coverage_amount' => null, 'age_prices' => [['id' => 801, 'age_from' => 0, 'age_to' => 120, 'price' => 12.5]]]);

    $result = PlanGeneratorUpgrades::perPersonForLabel([funerarioLine(), $optica], '31 a 65 años');

    expect($result['total'])->toBe(62.5)
        ->and(array_column($result['upgrades'], 'amount'))->toBe([50.0, 12.5])
        ->and($result['upgrades'][0]['upgrade_coverage_age_range_id'])->toBe(702);
});

it('suma los upgrades completos al primer pago de cada columna con tarifa; los pagos siguientes llevan solo el plan', function (): void {
    $columns = [
        ['column_key' => 'c1', 'header_label' => 'Especial US$ 5K'],
        ['column_key' => 'c2', 'header_label' => 'Sin tarifas'],
    ];
    $plan = 311.0 * 40 + 339 * 10;

    $totals = PlanGeneratorGroupTotalCalculator::totalsByColumn($columns, quoteRateRows(), 1700.0);

    expect($totals['annual']['c1'])->toBe($plan + 1700)
        ->and($totals['annual']['c2'])->toBe(0.0)
        ->and($totals['trimestral']['c1'])->toBe($plan / 4 + 1700)
        ->and($totals['trimestral_next']['c1'])->toBe($plan / 4)
        ->and($totals['semestral']['c1'])->toBe($plan / 2 + 1700)
        ->and($totals['semestral_next']['c1'])->toBe($plan / 2)
        ->and($totals['trimestral']['c2'])->toBe(0.0)
        ->and(PlanGeneratorGroupTotalCalculator::totalsByColumn($columns, quoteRateRows())['trimestral']['c1'])->toBe($plan / 4);
});

it('con upgrades abre cada frecuencia en primer pago y pagos siguientes; sin upgrades no cambia nada', function (): void {
    $withUpgrades = PlanGeneratorGroupTotalCalculator::displayRows(true, ['semestral'], 35.0);

    expect(array_column($withUpgrades, 'key'))->toBe(['annual', 'trimestral', 'trimestral_next', 'mensual', 'mensual_next'])
        ->and(array_column($withUpgrades, 'frequency_key'))->toBe(['annual', 'trimestral', 'trimestral', 'mensual', 'mensual'])
        ->and(array_column($withUpgrades, 'label'))->toBe(['Tarifa anual (pago único)', 'Tarifa Trimestral · 1.er pago', 'Tarifa Trimestral · pagos 2 a 4', 'Total Mensual · 1.er pago', 'Total Mensual · pagos 2 a 12'])
        ->and(array_column(PlanGeneratorGroupTotalCalculator::displayRows(false, [], 0.0), 'label'))->toBe(['Tarifa anual', 'Tarifa Semestral', 'Tarifa Trimestral']);
});

it('la sección de upgrades va entre el total grupal y las condiciones', function (): void {
    $quote = PlanGeneratorUpgrades::quote([funerarioLine()], quoteRateRows());

    expect(PlanGeneratorPdfPagination::sections('Condición', $quote))->toBe(['rates', 'group', 'upgrades', 'conditions'])
        ->and(PlanGeneratorPdfPagination::sections('', ['upgrades' => []]))->toBe(['rates', 'group']);
});

it('formatea los montos como el total grupal', function (): void {
    expect(PlanGeneratorUpgrades::money(48560.0))->toBe('$48.560')
        ->and(PlanGeneratorUpgrades::money(35.5))->toBe('$35,50')
        ->and(PlanGeneratorUpgrades::money(null))->toBe('—');
});

it('el PDF imprime cada upgrade con su cobertura, tarifas y servicios y lo suma al total grupal', function (): void {
    $plan = new PlanGenerator([
        'name' => 'PLAN ESPECIAL',
        'control_number' => '0003-1',
        'client_data' => 'CLIENTE',
        'population_summary' => '50',
        'population_unit' => 'poblacion',
        'brand_color' => '#1d4ed8',
    ]);
    $plan->id = 99;
    $plan->issued_at = Carbon::parse('2026-10-28');
    $plan->setRelation('upgrades', collect([new PlanGeneratorUpgrade(funerarioLine(['age_prices' => [['id' => 1, 'age_from' => 0, 'age_to' => 120, 'price' => 30]]]))]));

    $html = view('documents.plan-generator-preview', [
        'planGenerator' => $plan,
        'columns' => [['column_key' => 'c1', 'header_label' => 'Especial US$ 5K']],
        'rows' => ['r' => ['benefit_label' => 'CONSULTA', 'cells' => ['c1' => ['is_selected' => true, 'coverage_amount' => null]]]],
        'rateRows' => quoteRateRows(),
        'logoDataUri' => '',
        'generatedAt' => Carbon::parse('2026-10-28 09:00:00'),
        'brandColor' => '#1d4ed8',
        'brandColorBorder' => '#1e40af',
        'useQuotationBody' => false,
        'quotationPages' => [],
    ])->render();

    expect($html)
        ->toContain('ASISTENCIA DE SERVICIOS FUNERARIOS:')
        ->toContain('Cobertura $2.000')
        ->toContain('Tarifa anual individual por persona')
        ->toContain('$30')
        ->toContain('Tarifa anual grupal (50 personas)')
        ->toContain('$1.500')
        ->toContain('1. Traslado funerario (Dentro de Venezuela)')
        ->toContain('2. Tanatopraxia')
        ->toContain(PlanGeneratorGroupTotalCalculator::formatGroupTotal(311.0 * 40 + 339 * 10 + 1500))
        ->and(strpos($html, 'Total grupal'))->toBeLessThan(strpos($html, 'ASISTENCIA DE SERVICIOS FUNERARIOS:'));
});

it('un rango cubierto en parte se cobra solo a las personas declaradas, al precio del upgrade', function (): void {
    $rateRows = quoteRateRows();
    $rateRows['d'] = ['age_range_label' => 'Rango etario 80 - 90', 'population' => 6];
    $line = funerarioLine(['manual_ranges' => [
        ['label' => 'Rango etario 80 - 90', 'persons' => 4, 'note' => 'Según el padrón, 4 tienen entre 80 y 85', 'by' => 'Analista', 'at' => '2026-10-28T10:00:00-04:00'],
    ]]);

    $upgrade = PlanGeneratorUpgrades::quote([$line], $rateRows)['upgrades'][0];

    expect($upgrade['rows'][3]['status'])->toBe(PlanGeneratorUpgrades::STATUS_MANUAL)
        ->and($upgrade['rows'][3]['price'])->toBe(50.0)
        ->and($upgrade['rows'][3]['charged_persons'])->toBe(4)
        ->and($upgrade['rows'][3]['subtotal'])->toBe(200.0)
        ->and($upgrade['persons'])->toBe(54)
        ->and($upgrade['annual'])->toBe(1700.0 + 200);
});

it('declarar más personas de las que tiene el rango es un error', function (): void {
    $rateRows = quoteRateRows();
    $rateRows['d'] = ['age_range_label' => 'Rango etario 80 - 90', 'population' => 3];
    $line = funerarioLine(['manual_ranges' => [['label' => 'Rango etario 80 - 90', 'persons' => 5, 'note' => 'Según el padrón del cliente']]]);

    expect(PlanGeneratorUpgrades::quote([$line], $rateRows)['errors'][0])->toContain('declaró 5 personas')
        ->and(PlanGeneratorUpgrades::quote([$line], $rateRows)['upgrades'][0]['rows'][3]['charged_persons'])->toBe(3);
});

it('no se puede incluir a mano un rango sin ninguna edad cubierta ni pisar uno cubierto', function (): void {
    $rateRows = quoteRateRows();
    $rateRows['d'] = ['age_range_label' => 'Rango etario 86 - 95', 'population' => 3];
    $line = funerarioLine(['manual_ranges' => [
        ['label' => 'Rango etario 86 - 95', 'persons' => 3, 'note' => 'Intento de forzar edades no cubiertas'],
        ['label' => 'Rango etario 0 - 30', 'persons' => 1, 'note' => 'Intento de bajar la cantidad cobrada'],
    ]]);

    $rows = PlanGeneratorUpgrades::quote([$line], $rateRows)['upgrades'][0]['rows'];

    expect($rows[3]['status'])->toBe(PlanGeneratorUpgrades::STATUS_NO_PRICE)
        ->and($rows[3]['charged_persons'])->toBe(0)
        ->and($rows[0]['status'])->toBe(PlanGeneratorUpgrades::STATUS_INCLUDED)
        ->and($rows[0]['charged_persons'])->toBe(40);
});

it('al aprobar, cada persona recibe el upgrade solo si su edad real está cubierta', function (): void {
    $line = funerarioLine([
        'age_prices' => [['id' => 1, 'age_from' => 18, 'age_to' => 64, 'price' => 35]],
        'manual_ranges' => [['label' => '0 a 30 años', 'persons' => 10, 'note' => 'Según el padrón del cliente']],
        'excluded_ranges' => [],
    ]);
    $rateRows = [
        ['age_range_label' => '0 a 30 años', 'population' => 13],
        ['age_range_label' => '31 a 65 años', 'population' => 31],
        ['age_range_label' => '66 a 74 años', 'population' => 1],
    ];
    $receives = fn (?int $age): bool => PlanGeneratorUpgrades::eligibilityForAge([$line], $age, PlanGeneratorUpgrades::rowLabelForAge($rateRows, $age))[0]['receives'];

    expect($receives(25))->toBeTrue()
        ->and($receives(10))->toBeFalse()
        ->and($receives(64))->toBeFalse()
        ->and($receives(65))->toBeFalse()
        ->and($receives(70))->toBeFalse()
        ->and($receives(null))->toBeFalse()
        ->and(PlanGeneratorUpgrades::eligibilityForAge([$line], 10, '0 a 30 años')[0]['reason'])->toBe('tiene 10 años y el upgrade cubre 18 a 64 años')
        ->and(PlanGeneratorUpgrades::eligibilityForAge([$line], 40, '31 a 65 años')[0]['reason'])->toContain('no se incluyó');

    $withAll = $line;
    $withAll['manual_ranges'][] = ['label' => '31 a 65 años', 'persons' => 30, 'note' => 'Según el padrón del cliente'];

    expect(PlanGeneratorUpgrades::perPersonForAge([$withAll], 40, '31 a 65 años')['total'])->toBe(35.0)
        ->and(PlanGeneratorUpgrades::perPersonForAge([$withAll], 65, '31 a 65 años')['upgrades'])->toBe([]);
});

it('en un rango cubierto completo el precio al aprobar es el de la edad real', function (): void {
    $line = funerarioLine();

    expect(PlanGeneratorUpgrades::perPersonForAge([$line], 40, 'Rango etario 31 - 65')['total'])->toBe(30.0)
        ->and(PlanGeneratorUpgrades::perPersonForAge([$line], 65, 'Rango etario 31 - 65')['total'])->toBe(50.0)
        ->and(PlanGeneratorUpgrades::perPersonForAge([funerarioLine(['excluded_ranges' => ['Rango etario 31 - 65']])], 40, 'Rango etario 31 - 65')['upgrades'])->toBe([]);
});

it('describe las edades que cubre el upgrade', function (): void {
    expect(PlanGeneratorUpgrades::coverageSpansLabel([['age_from' => 18, 'age_to' => 30], ['age_from' => 40, 'age_to' => 64]]))->toBe('18 a 30 y 40 a 64 años')
        ->and(PlanGeneratorUpgrades::coverageSpansLabel([]))->toBe('ninguna edad');
});

it('en el PDF el rango incluido a mano sale como uno más, sin la nota', function (): void {
    $plan = new PlanGenerator(['name' => 'PLAN', 'brand_color' => '#1d4ed8', 'population_unit' => 'poblacion']);
    $plan->id = 98;
    $plan->issued_at = Carbon::parse('2026-10-28');
    $plan->setRelation('upgrades', collect([new PlanGeneratorUpgrade(funerarioLine([
        'age_prices' => [['id' => 1, 'age_from' => 0, 'age_to' => 64, 'price' => 35]],
        'manual_ranges' => [['label' => '60 a 69 años', 'persons' => 1, 'note' => 'Nota interna que no debe salir']],
    ]))]));

    $html = view('documents.plan-generator-preview', [
        'planGenerator' => $plan,
        'columns' => [['column_key' => 'c1', 'header_label' => 'Especial 20K']],
        'rows' => ['r' => ['benefit_label' => 'CONSULTA', 'cells' => ['c1' => ['is_selected' => true, 'coverage_amount' => null]]]],
        'rateRows' => [
            'a' => ['age_range_label' => '20 a 29 años', 'population' => 2, 'cells' => ['c1' => ['rate_amount' => 600]]],
            'b' => ['age_range_label' => '60 a 69 años', 'population' => 1, 'cells' => ['c1' => ['rate_amount' => 1300]]],
        ],
        'logoDataUri' => '',
        'generatedAt' => Carbon::parse('2026-10-28 09:00:00'),
        'brandColor' => '#1d4ed8',
        'brandColorBorder' => '#1e40af',
        'useQuotationBody' => false,
        'quotationPages' => [],
    ])->render();

    expect($html)
        ->toContain('Tarifa anual individual por persona')
        ->toContain('$35')
        ->toContain('Tarifa anual grupal (3 personas)')
        ->toContain('$105')
        ->not->toContain('Nota interna que no debe salir');
});
