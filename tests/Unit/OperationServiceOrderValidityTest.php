<?php

declare(strict_types=1);

use App\Models\OperationServiceOrder;
use App\Support\Operations\OperationServiceOrderValidity;
use App\Support\Operations\OperationServiceOrderViewActions;
use Illuminate\Support\Carbon;

it('caduca ordenes no finalizadas despues de 10 dias de aprobacion', function (): void {
    Carbon::setTestNow('2026-06-15 12:00:00');

    $expired = new OperationServiceOrder;
    $expired->setRawAttributes([
        'status' => 'EN GESTION',
        'approved_at' => '2026-06-01 10:00:00',
    ], true);

    $active = new OperationServiceOrder;
    $active->setRawAttributes([
        'status' => 'EN GESTION',
        'approved_at' => '2026-06-10 10:00:00',
    ], true);

    $finalized = new OperationServiceOrder;
    $finalized->setRawAttributes([
        'status' => 'FINALIZADO',
        'approved_at' => '2026-05-01 10:00:00',
    ], true);

    expect(OperationServiceOrderValidity::isExpired($expired))->toBeTrue()
        ->and(OperationServiceOrderValidity::isExpired($active))->toBeFalse()
        ->and(OperationServiceOrderValidity::isExpired($finalized))->toBeFalse()
        ->and(OperationServiceOrderValidity::expiresAt($active)?->format('Y-m-d'))->toBe('2026-06-20')
        ->and(OperationServiceOrderValidity::remainingDays($active))->toBe(5);
});

it('define tonos y etiquetas cortas para resaltar la vigencia', function (): void {
    Carbon::setTestNow('2026-06-15 12:00:00');

    $urgent = new OperationServiceOrder;
    $urgent->setRawAttributes([
        'status' => 'EN GESTION',
        'approved_at' => '2026-06-07 10:00:00',
    ], true);

    $stable = new OperationServiceOrder;
    $stable->setRawAttributes([
        'status' => 'EN GESTION',
        'approved_at' => '2026-06-10 10:00:00',
    ], true);

    $expired = new OperationServiceOrder;
    $expired->setRawAttributes([
        'status' => 'CADUCADA',
        'approved_at' => '2026-06-01 10:00:00',
    ], true);

    $closed = new OperationServiceOrder;
    $closed->setRawAttributes([
        'status' => 'FINALIZADO',
        'approved_at' => '2026-06-01 10:00:00',
    ], true);

    expect(OperationServiceOrderValidity::vigenciaTone($urgent))->toBe('warning')
        ->and(OperationServiceOrderValidity::vigenciaShortLabel($urgent))->toBe('Vence en 2 días')
        ->and(OperationServiceOrderValidity::vigenciaTone($stable))->toBe('info')
        ->and(OperationServiceOrderValidity::vigenciaShortLabel($stable))->toBe('Vence en 5 días')
        ->and(OperationServiceOrderValidity::vigenciaTone($expired))->toBe('danger')
        ->and(OperationServiceOrderValidity::shouldHighlightVigencia($expired))->toBeTrue()
        ->and(OperationServiceOrderValidity::shouldHighlightVigencia($closed))->toBeFalse();
});

it('define CADUCADA como estatus cerrado', function (): void {
    expect(OperationServiceOrderValidity::closedStatuses())->toContain('CADUCADA')
        ->and(OperationServiceOrderViewActions::canCancel(new OperationServiceOrder(['status' => 'CADUCADA'])))->toBeFalse()
        ->and(OperationServiceOrderViewActions::canFinalize(new OperationServiceOrder(['status' => 'CADUCADA'])))->toBeFalse();
});

it('integra caducidad en tabla, job y acciones cerradas', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Tables/OperationServiceOrdersTable.php');
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/ExpireOperationServiceOrders.php');
    $console = file_get_contents(dirname(__DIR__, 2).'/routes/console.php');
    $viewActions = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/OperationServiceOrderViewActions.php');

    expect($table)
        ->toContain('OperationServiceOrderValidity::expireEligibleOrders')
        ->toContain("'CADUCADA' => 'danger'")
        ->toContain('border-red-500 bg-red-50/90');

    expect($job)->toContain('OperationServiceOrderValidity::expireEligibleOrders');

    expect($console)->toContain('ExpireOperationServiceOrders');

    expect($viewActions)->toContain('OperationServiceOrderValidity::closedStatuses()');
});
