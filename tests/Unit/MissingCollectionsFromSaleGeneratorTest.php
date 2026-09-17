<?php

declare(strict_types=1);

use App\Services\AffiliationRenewalCollectionGenerator;
use App\Services\MissingCollectionsFromSaleGenerator;
use Carbon\Carbon;

it('calcula el ciclo semestral y omite la cuota ya pagada', function (): void {
    $activation = Carbon::create(2025, 1, 29)->startOfDay();
    $paymentDate = Carbon::create(2026, 1, 26)->startOfDay();

    $cycleStart = MissingCollectionsFromSaleGenerator::cycleStart($activation, $paymentDate);
    $pending = MissingCollectionsFromSaleGenerator::pendingInstallmentDates($cycleStart, 'SEMESTRAL', $paymentDate);

    expect($cycleStart->format('d/m/Y'))->toBe('29/01/2026')
        ->and($pending)->toHaveCount(1)
        ->and($pending[0]->format('d/m/Y'))->toBe('29/07/2026');
});

it('si la unica cuota del ciclo ya se pago, programa la siguiente', function (): void {
    $cycleStart = Carbon::create(2026, 1, 29)->startOfDay();
    $paymentDate = Carbon::create(2026, 1, 26)->startOfDay();

    $pending = MissingCollectionsFromSaleGenerator::pendingInstallmentDates($cycleStart, 'ANUAL', $paymentDate);

    expect($pending)->toHaveCount(1)
        ->and($pending[0]->format('d/m/Y'))->toBe('29/01/2027');
});

it('reutiliza las fechas de renovacion para trimestral', function (): void {
    $cycleStart = Carbon::create(2026, 2, 16)->startOfDay();
    $paymentDate = Carbon::create(2026, 2, 10)->startOfDay();

    $pending = MissingCollectionsFromSaleGenerator::pendingInstallmentDates($cycleStart, 'TRIMESTRAL', $paymentDate);
    $all = AffiliationRenewalCollectionGenerator::upcomingPaymentDates($cycleStart, 'TRIMESTRAL');

    expect($all)->toHaveCount(4)
        ->and($pending)->toHaveCount(3)
        ->and($pending[0]->format('d/m/Y'))->toBe('16/05/2026');
});

it('el generador no crea ventas ni aprueba pagos', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Services/MissingCollectionsFromSaleGenerator.php');

    expect($source)
        ->toContain('No crea ni duplica ventas')
        ->toContain("status = 'POR PAGAR'")
        ->not->toContain('new Sale')
        ->not->toContain('Sale::query()->create')
        ->not->toContain('PaidMembership')
        ->not->toContain('approvePayment');
});

it('el comando exige --execute y no duplica la venta', function (): void {
    $command = file_get_contents(dirname(__DIR__, 2).'/app/Console/Commands/GenerateMissingCollectionsFromSaleCommand.php');

    expect($command)
        ->toContain('collections:generate-from-sale')
        ->toContain('--execute')
        ->toContain('No duplica la venta')
        ->toContain('MissingCollectionsFromSaleGenerator');
});
