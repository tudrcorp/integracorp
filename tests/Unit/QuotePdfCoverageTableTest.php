<?php

declare(strict_types=1);

use App\Support\QuotePdfCoverageTable;

it('construye columnas alineadas por cobertura sin usar ceros', function (): void {
    $grouped = [
        '1 A 25' => [
            (object) ['coverage' => 1000, 'subtotal_anual' => 240, 'total_persons' => 2],
        ],
        '26 a 50' => [
            (object) ['coverage' => 2000, 'subtotal_anual' => 480, 'total_persons' => 3],
        ],
    ];

    $table = QuotePdfCoverageTable::build($grouped);

    expect($table['coverageCount'])->toBe(2);
    expect($table['rows'][0]['amounts'])->toBe(['1000' => 240.0]);
    expect($table['rows'][1]['amounts'])->toBe(['2000' => 480.0]);
    expect($table['totals']['1000'])->toBe(240.0);
    expect($table['totals']['2000'])->toBe(480.0);
});

it('construye cinco columnas cuando el detalle trae cinco coberturas', function (): void {
    $grouped = [
        '0 a 45' => [
            (object) ['coverage' => 1000, 'subtotal_anual' => 378, 'total_persons' => 2],
            (object) ['coverage' => 2000, 'subtotal_anual' => 432, 'total_persons' => 2],
            (object) ['coverage' => 3000, 'subtotal_anual' => 486, 'total_persons' => 2],
            (object) ['coverage' => 5000, 'subtotal_anual' => 648, 'total_persons' => 2],
            (object) ['coverage' => 10000, 'subtotal_anual' => 756, 'total_persons' => 2],
        ],
    ];

    $table = QuotePdfCoverageTable::build($grouped);

    expect($table['coverageCount'])->toBe(5);
    expect($table['rows'][0]['amounts'])->toHaveCount(5);
});

it('no asigna totales en cero para columnas vacias', function (): void {
    $grouped = [
        '1 A 25' => [
            (object) ['coverage' => 1000, 'subtotal_anual' => 100, 'total_persons' => 1],
        ],
    ];

    $table = QuotePdfCoverageTable::build($grouped);

    expect($table['totals']['1000'])->toBe(100.0);
    expect(collect($table['totals'])->contains(fn (?float $total): bool => $total === 0.0))->toBeFalse();
});

it('formatea etiquetas de cobertura en miles', function (): void {
    expect(QuotePdfCoverageTable::formatLabel(1000))->toBe('1K');
    expect(QuotePdfCoverageTable::formatLabel(50000))->toBe('50K');
});

it('resuelve columnas desde el count de coberturas del plan', function (): void {
    $planId = \App\Models\Coverage::query()->whereNotNull('plan_id')->value('plan_id');

    if ($planId === null) {
        $this->markTestSkipped('No hay coberturas configuradas en la base de datos.');
    }

    $expectedCount = \App\Models\Coverage::query()->where('plan_id', $planId)->count();
    $columns = QuotePdfCoverageTable::resolveCoverageColumns((int) $planId, []);

    expect($columns)->toHaveCount($expectedCount);
})->group('integration-db');
