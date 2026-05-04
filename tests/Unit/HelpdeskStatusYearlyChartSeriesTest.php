<?php

declare(strict_types=1);

use App\Support\HelpdeskStatusYearlyChartSeries;
use Carbon\Carbon;

it('genera series mensuales por estatus', function () {
    $year = 2026;

    $records = collect([
        (object) ['status' => 'PENDIENTE POR INICIAR', 'created_at' => Carbon::create($year, 1, 5, 12)],
        (object) ['status' => 'PENDIENTE POR INICIAR', 'created_at' => Carbon::create($year, 1, 20, 9)],
        (object) ['status' => 'EN PROCESO', 'created_at' => Carbon::create($year, 2, 3, 18)],
        (object) ['status' => 'TERMINADO', 'created_at' => Carbon::create($year, 12, 31, 23)],
        (object) ['status' => 'CANCELADO', 'created_at' => Carbon::create($year, 1, 1, 0)],
        (object) ['status' => 'EN PROCESO', 'created_at' => Carbon::create($year - 1, 2, 3, 18)],
    ]);

    $data = HelpdeskStatusYearlyChartSeries::chartJsDataFromRecords($records, year: $year);

    expect($data['labels'])->toHaveCount(12);
    expect($data['datasets'])->toHaveCount(3);

    $datasetsByLabel = collect($data['datasets'])->keyBy('label');

    expect($datasetsByLabel['PENDIENTE POR INICIAR']['data'][0])->toBe(2);
    expect($datasetsByLabel['PENDIENTE POR INICIAR']['data'][1])->toBe(0);

    expect($datasetsByLabel['EN PROCESO']['data'][1])->toBe(1);
    expect($datasetsByLabel['EN PROCESO']['data'][0])->toBe(0);

    expect($datasetsByLabel['TERMINADO']['data'][11])->toBe(1);
});

it('genera detalle por colaborador y estatus ordenado por terminados', function () {
    $records = collect([
        (object) [
            'status' => 'PENDIENTE POR INICIAR',
            'rrhhColaboradores' => [
                (object) ['fullName' => 'Ana Perez'],
                (object) ['fullName' => 'Luis Ruiz'],
            ],
        ],
        (object) [
            'status' => 'EN PROCESO',
            'rrhhColaboradores' => [
                (object) ['fullName' => 'Ana Perez'],
            ],
        ],
        (object) [
            'status' => 'TERMINADO',
            'rrhhColaboradores' => [
                (object) ['fullName' => 'Luis Ruiz'],
            ],
        ],
        (object) [
            'status' => 'TERMINADO',
            'rrhhColaboradores' => [
                (object) ['fullName' => 'Luis Ruiz'],
            ],
        ],
        (object) [
            'status' => 'TERMINADO',
            'rrhhColaboradores' => [
                (object) ['fullName' => 'Ana Perez'],
            ],
        ],
    ]);

    $rows = HelpdeskStatusYearlyChartSeries::detailRowsFromRecords($records);

    expect($rows)->toHaveCount(2);
    expect($rows[0]['colaborador'])->toBe('Luis Ruiz');
    expect($rows[1]['colaborador'])->toBe('Ana Perez');

    $byName = collect($rows)->keyBy('colaborador');

    expect($byName['Ana Perez']['totals']['PENDIENTE POR INICIAR'])->toBe(1);
    expect($byName['Ana Perez']['totals']['EN PROCESO'])->toBe(1);
    expect($byName['Ana Perez']['totals']['TERMINADO'])->toBe(1);
    expect($byName['Ana Perez']['total'])->toBe(3);

    expect($byName['Luis Ruiz']['totals']['PENDIENTE POR INICIAR'])->toBe(1);
    expect($byName['Luis Ruiz']['totals']['TERMINADO'])->toBe(2);
    expect($byName['Luis Ruiz']['total'])->toBe(3);
});

it('genera data de chart para detalle ordenado por terminados', function () {
    $records = collect([
        (object) [
            'status' => 'TERMINADO',
            'rrhhColaboradores' => [
                (object) ['fullName' => 'Bruno'],
            ],
        ],
        (object) [
            'status' => 'EN PROCESO',
            'rrhhColaboradores' => [
                (object) ['fullName' => 'Ana'],
            ],
        ],
        (object) [
            'status' => 'TERMINADO',
            'rrhhColaboradores' => [
                (object) ['fullName' => 'Ana'],
            ],
        ],
        (object) [
            'status' => 'TERMINADO',
            'rrhhColaboradores' => [
                (object) ['fullName' => 'Ana'],
            ],
        ],
    ]);

    $data = HelpdeskStatusYearlyChartSeries::detailChartDataFromRecords($records);
    $datasetsByLabel = collect($data['datasets'])->keyBy('label');

    expect($data['labels'])->toBe(['Ana', 'Bruno']);
    expect($datasetsByLabel['TERMINADO']['data'])->toBe([2, 1]);
    expect($datasetsByLabel['EN PROCESO']['data'])->toBe([1, 0]);
});
