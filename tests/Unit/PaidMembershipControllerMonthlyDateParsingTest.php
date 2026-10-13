<?php

declare(strict_types=1);

use App\Http\Controllers\PaidMembershipController;
use Carbon\Carbon;

it('interpreta la próxima fecha de pago con guiones, barras o iso', function (string $value, string $expected): void {
    $method = new ReflectionMethod(PaidMembershipController::class, 'parseDateForStorage');

    $parsed = $method->invoke(null, $value);

    expect($parsed)->toBeInstanceOf(Carbon::class)
        ->and($parsed->format('Y-m-d'))->toBe($expected);
})->with([
    'guiones' => ['13-10-2027', '2027-10-13'],
    'barras' => ['13/10/2027', '2027-10-13'],
    'iso' => ['2027-10-13', '2027-10-13'],
]);

it('rechaza una fecha vacía o con formato desconocido', function (string $value): void {
    $method = new ReflectionMethod(PaidMembershipController::class, 'parseDateForStorage');

    $method->invoke(null, $value);
})->with([
    'vacía' => [''],
    'texto' => ['sin-fecha'],
])->throws(InvalidArgumentException::class);

it('el primer pago mensual individual genera cada cuota un mes después y usa el correlativo de cobranza', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/PaidMembershipController.php');

    expect($source)
        ->toContain("self::parseDateForStorage((string) \$prox_date)->addMonthNoOverflow()->format('d/m/Y')")
        ->toContain('UtilsController::generateCorrelativeCollection($lastInvoiceNumberCollection)')
        ->not->toContain('generateCorrelativeCollection($lastInvoiceNumber->invoice_number)')
        ->not->toContain('$collections->next_payment_date = $record->prox_payment_date');
});
