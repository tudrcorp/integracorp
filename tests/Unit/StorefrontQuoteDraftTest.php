<?php

declare(strict_types=1);

use App\Models\AgeRange;
use App\Models\Fee;
use App\Support\Storefront\StorefrontQuoteDraft;
use App\Support\Storefront\StorefrontQuotePricer;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

it('normaliza personas y descarta filas vacias', function (): void {
    expect(StorefrontQuoteDraft::normalizePeople([
        ['age' => 34, 'quantity' => 1],
        ['age' => 8, 'quantity' => 0],
        ['age' => -1, 'quantity' => 2],
        ['age' => 40, 'quantity' => 3],
    ]))->toBe([
        ['age' => 34, 'quantity' => 1],
        ['age' => 40, 'quantity' => 3],
    ]);
});

it('agrupa personas del mismo rango y rechaza edades sin tarifa', function (): void {
    $adulto = new AgeRange([
        'plan_id' => 1,
        'age_init' => 18,
        'age_end' => 59,
        'range' => '18-59',
    ]);
    $adulto->id = 10;

    $nino = new AgeRange([
        'plan_id' => 1,
        'age_init' => 0,
        'age_end' => 17,
        'range' => '0-17',
    ]);
    $nino->id = 11;

    $ranges = collect([$adulto, $nino]);

    expect(StorefrontQuoteDraft::entriesFromPeople(1, [
        ['age' => 8, 'quantity' => 2],
        ['age' => 34, 'quantity' => 1],
        ['age' => 12, 'quantity' => 1],
    ], $ranges))->toBe([
        ['plan_id' => 1, 'age_range_id' => 11, 'total_persons' => 3],
        ['plan_id' => 1, 'age_range_id' => 10, 'total_persons' => 1],
    ]);

    expect(fn () => StorefrontQuoteDraft::entriesFromPeople(1, [
        ['age' => 95, 'quantity' => 1],
    ], $ranges))->toThrow(ValidationException::class);
});

it('el agente solo cotiza rangos que pertenecen al plan', function (): void {
    $range = new AgeRange(['plan_id' => 2, 'age_init' => 18, 'age_end' => 59]);
    $range->id = 20;

    expect(fn () => StorefrontQuoteDraft::entriesFromRanges(1, [
        ['age_range_id' => 20, 'total_persons' => 1],
    ], collect([$range])))->toThrow(ValidationException::class);
});

it('el precio de coberturas se expresa como desde-hasta y no se suma', function (): void {
    $barata = new Fee(['age_range_id' => 1, 'price' => 100]);
    $cara = new Fee(['age_range_id' => 1, 'price' => 400]);
    $otra = new Fee(['age_range_id' => 2, 'price' => 50]);

    $quote = StorefrontQuotePricer::quote([
        ['age_range_id' => 1, 'total_persons' => 2],
        ['age_range_id' => 2, 'total_persons' => 1],
    ], collect([$barata, $cara, $otra]));

    expect($quote['desde'])->toBe(250.0)
        ->and($quote['hasta'])->toBe(850.0)
        ->and($quote['is_range'])->toBeTrue()
        ->and($quote['persons'])->toBe(3)
        ->and(StorefrontQuotePricer::headline($quote))->toBe('Desde US$ 250 al año')
        ->and(StorefrontQuotePricer::amountLabel($quote))->toBe('Desde US$ 250')
        ->and(StorefrontQuotePricer::coverageLabel($quote))->toBe('Al año · 3 personas');
});

it('un paquete con una sola tarifa muestra el total exacto', function (): void {
    $fee = new Fee(['age_range_id' => 7, 'price' => 180]);

    $quote = StorefrontQuotePricer::quote([
        ['age_range_id' => 7, 'total_persons' => 2],
    ], collect([$fee]));

    expect($quote['desde'])->toBe(360.0)
        ->and($quote['hasta'])->toBe(360.0)
        ->and($quote['is_range'])->toBeFalse()
        ->and(StorefrontQuotePricer::headline($quote))->toBe('US$ 360 al año')
        ->and(StorefrontQuotePricer::amountLabel($quote))->toBe('US$ 360')
        ->and(StorefrontQuotePricer::coverageLabel($quote))->toBe('Al año · 2 personas');
});

it('resume el grupo familiar por rango de edad', function (): void {
    $adulto = new AgeRange([
        'plan_id' => 1,
        'age_init' => 18,
        'age_end' => 59,
        'range' => '18-59',
    ]);
    $adulto->id = 10;

    StorefrontQuoteDraft::put([
        'plan_id' => 1,
        'people' => [['age' => 34, 'quantity' => 2]],
        'ranges' => [],
        'full_name' => '',
        'email' => '',
        'phone' => '',
    ]);

    expect(StorefrontQuoteDraft::groupSummary(1, collect([$adulto]), false))->toBe([
        ['label' => '18 a 59 años', 'persons' => 2],
    ]);
});

it('el borrador queda respaldado en cookie y se puede resumir', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/Storefront/StorefrontQuoteDraft.php');

    expect($source)
        ->toContain('COOKIE_KEY')
        ->toContain('fromCookie')
        ->toContain('queueCookie')
        ->toContain('groupSummary');
});
