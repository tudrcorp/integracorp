<?php

declare(strict_types=1);

use App\Models\OperationCoordinationService;
use App\Models\OperationServiceFlowEvent;
use App\Models\OperationServiceOrder;
use App\Support\Operations\ExpiredServiceOrderRestorer;
use App\Support\Operations\OperationServiceOrderValidity;
use App\Support\Operations\ServiceOrderFlowTrace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

/**
 * Restauración de órdenes CADUCADAS. Todo corre dentro de una transacción que
 * siempre se revierte; los PDF y el CSV van a discos falsos.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Storage::fake('public');
    Storage::fake('local');
    OperationServiceOrderValidity::overrideSettings(false);

    $this->coordinationId = OperationCoordinationService::query()->value('id');

    if ($this->coordinationId === null) {
        $this->markTestSkipped('La base no tiene coordinaciones para colgar órdenes de prueba.');
    }
});

afterEach(function (): void {
    OperationServiceOrderValidity::overrideSettings(null);
    DB::rollBack();
});

function expiredTestOrder(?string $tracedPreviousStatus = null): OperationServiceOrder
{
    $order = OperationServiceOrder::query()->forceCreate([
        'operation_coordination_service_id' => test()->coordinationId,
        'order_number' => 'ORD-QA-RST-'.Str::upper(Str::random(8)),
        'service_type' => 'LABORATORIOS',
        'status' => 'CADUCADA',
        'approved_at' => now()->subDays(40),
        'created_by' => 'QA',
    ]);

    if ($tracedPreviousStatus !== null) {
        OperationServiceFlowEvent::query()->create([
            'operation_coordination_service_id' => test()->coordinationId,
            'operation_service_order_id' => $order->id,
            'event' => ServiceOrderFlowTrace::ORDER_EXPIRED,
            'description' => 'QA caducó',
            'changes' => ['status' => ['antes' => $tracedPreviousStatus, 'despues' => 'CADUCADA']],
            'actor_name' => 'SISTEMA',
        ]);
    }

    return $order;
}

it('toma el estado anterior de la traza y, sin traza, vuelve a EN GESTION', function (): void {
    $traced = expiredTestOrder('EN GESTION');
    $untraced = expiredTestOrder();
    $tracedClosed = expiredTestOrder('FINALIZADO');

    $previous = ExpiredServiceOrderRestorer::previousStatuses([$traced->id, $untraced->id, $tracedClosed->id]);

    expect($previous[$traced->id])->toBe(['status' => 'EN GESTION', 'source' => 'traza'])
        ->and($previous[$untraced->id])->toBe(['status' => 'EN GESTION', 'source' => 'supuesto'])
        ->and($previous[$tracedClosed->id]['status'])->toBe('EN GESTION');
});

it('simula sin cambiar nada', function (): void {
    $order = expiredTestOrder('EN GESTION');

    $this->artisan('operation-service-orders:restore-expired', ['--order' => [(string) $order->id]])
        ->expectsOutputToContain('Simulación')
        ->assertSuccessful();

    expect($order->refresh()->status)->toBe('CADUCADA');
});

it('se niega a aplicar si la caducidad vigente las volvería a caducar', function (): void {
    OperationServiceOrderValidity::overrideSettings(true, 10);
    $order = expiredTestOrder('EN GESTION');

    $this->artisan('operation-service-orders:restore-expired', ['--apply' => true, '--order' => [(string) $order->id]])
        ->expectsOutputToContain('volverían a quedar CADUCADAS')
        ->assertFailed();

    expect($order->refresh()->status)->toBe('CADUCADA');
});

it('restaura, deja traza, regenera el PDF sin la marca y guarda el CSV', function (): void {
    $order = expiredTestOrder('EN GESTION');

    $this->artisan('operation-service-orders:restore-expired', ['--apply' => true, '--order' => [$order->order_number]])
        ->assertSuccessful();

    $order->refresh();
    $event = OperationServiceFlowEvent::query()->where('operation_service_order_id', $order->id)->latest('id')->first();

    expect($order->status)->toBe('EN GESTION')
        ->and($order->updated_by)->toBe('Restauración de órdenes caducadas')
        ->and($order->service_order_pdf_path)->not->toBeEmpty()
        ->and(Storage::disk('public')->exists($order->service_order_pdf_path))->toBeTrue()
        ->and(ServiceOrderFlowTrace::closingNoticeForOrder($order))->toBeNull()
        ->and($event->event)->toBe(ServiceOrderFlowTrace::ORDER_STATUS_CHANGED)
        ->and($event->reason)->toBe(ExpiredServiceOrderRestorer::REASON)
        ->and(Storage::disk('local')->files('operation-service-orders'))->toHaveCount(1);
});

it('es idempotente: una orden que ya no está CADUCADA no se toca', function (): void {
    $order = expiredTestOrder();

    expect(ExpiredServiceOrderRestorer::restore($order, 'EN GESTION', 'QA'))->toBeTrue()
        ->and(ExpiredServiceOrderRestorer::restore($order, 'EN GESTION', 'QA'))->toBeFalse();
});

it('nunca restaura a un estado cerrado', function (): void {
    $order = expiredTestOrder();

    ExpiredServiceOrderRestorer::restore($order, 'FINALIZADO', 'QA');

    expect($order->refresh()->status)->toBe('EN GESTION');
});

it('con --without-pdf no genera PDF', function (): void {
    $order = expiredTestOrder();

    $this->artisan('operation-service-orders:restore-expired', ['--apply' => true, '--without-pdf' => true, '--order' => [(string) $order->id]])
        ->assertSuccessful();

    expect($order->refresh()->status)->toBe('EN GESTION')
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('rechaza un --user inexistente', function (): void {
    $this->artisan('operation-service-orders:restore-expired', ['--user' => '99999999'])
        ->assertFailed();
});
