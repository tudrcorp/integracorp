<?php

declare(strict_types=1);

use App\Support\WhiteCompanies\WhiteCompanyNegotiatedFeesBulkCreator;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

it('normaliza varias tarifas para una carga masiva', function (): void {
    $prepared = WhiteCompanyNegotiatedFeesBulkCreator::normalize([
        ['fee_id' => 2, 'sale_price' => 250, 'neta' => 189],
        ['fee_id' => '62', 'sale_price' => '280', 'neta' => '216'],
    ]);

    expect($prepared)->toHaveCount(2)
        ->and($prepared[0])->toBe([
            'fee_id' => 2,
            'sale_price' => 250.0,
            'neta' => 189.0,
        ])
        ->and($prepared[1]['fee_id'])->toBe(62)
        ->and($prepared[1]['neta'])->toBe(216.0);
});

it('rechaza una carga vacia', function (): void {
    try {
        WhiteCompanyNegotiatedFeesBulkCreator::normalize([]);
        expect(false)->toBeTrue();
    } catch (ValidationException $exception) {
        expect($exception->errors()['items'][0])->toBe('Debe agregar al menos una tarifa.');
    }
});

it('rechaza tarifas repetidas en la misma carga', function (): void {
    try {
        WhiteCompanyNegotiatedFeesBulkCreator::normalize([
            ['fee_id' => 62, 'sale_price' => 280, 'neta' => 216],
            ['fee_id' => 62, 'sale_price' => 300, 'neta' => 216],
        ]);
        expect(false)->toBeTrue();
    } catch (ValidationException $exception) {
        expect($exception->errors()['items.1.fee_id'][0])->toBe('Esta tarifa ya tiene neta pactada para la empresa aliada.');
    }
});

it('rechaza una tarifa que ya esta pactada para la empresa', function (): void {
    try {
        WhiteCompanyNegotiatedFeesBulkCreator::normalize(
            [['fee_id' => 62, 'sale_price' => 280, 'neta' => 216]],
            [62],
        );
        expect(false)->toBeTrue();
    } catch (ValidationException $exception) {
        expect($exception->errors()['items.0.fee_id'][0])->toBe('Esta tarifa ya tiene neta pactada para la empresa aliada.');
    }
});

it('rechaza neta mayor que el precio de venta', function (): void {
    try {
        WhiteCompanyNegotiatedFeesBulkCreator::normalize([
            ['fee_id' => 62, 'sale_price' => 200, 'neta' => 216],
        ]);
        expect(false)->toBeTrue();
    } catch (ValidationException $exception) {
        expect($exception->errors()['items.0.neta'][0])->toBe('La neta no puede ser mayor que el precio de venta.');
    }
});

it('exige tarifa, venta y neta en cada fila', function (array $item, string $field, string $message): void {
    try {
        WhiteCompanyNegotiatedFeesBulkCreator::normalize([$item]);
        expect(false)->toBeTrue();
    } catch (ValidationException $exception) {
        expect($exception->errors()[$field][0])->toBe($message);
    }
})->with([
    'sin tarifa' => [['sale_price' => 280, 'neta' => 216], 'items.0.fee_id', 'Debe seleccionar la tarifa del catálogo.'],
    'sin venta' => [['fee_id' => 62, 'neta' => 216], 'items.0.sale_price', 'Debe indicar el precio de venta.'],
    'sin neta' => [['fee_id' => 62, 'sale_price' => 280], 'items.0.neta', 'Debe indicar la neta.'],
]);
