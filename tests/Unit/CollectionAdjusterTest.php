<?php

declare(strict_types=1);

use App\Models\Collection;
use App\Models\CollectionAdjustment;
use App\Models\CreditReconciliation;
use App\Models\User;
use App\Support\Collections\CollectionAdjuster;
use App\Support\Collections\CollectionAdjustmentAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Estos tests escriben, así que corren dentro de una transacción que siempre se
 * revierte, igual que PlanStructurePersistenceTest. No se deja nada en la base.
 */
beforeEach(function (): void {
    DB::beginTransaction();
});

afterEach(function (): void {
    DB::rollBack();
});

function adjusterTestUser(array $departments = ['ADMINISTRACION']): User
{
    return User::factory()->make(['name' => 'Analista de prueba', 'departament' => $departments]);
}

function adjusterTestCollection(array $attributes = []): Collection
{
    return Collection::query()->forceCreate([
        'include_date' => '15/04/2025',
        'owner_code' => 'TDG-100',
        'code_agency' => 'TDG-100',
        'collection_invoice_number' => 'TEST-ADJ-'.uniqid(),
        'quote_number' => 'N/A',
        'affiliation_code' => 'TEST-ADJ-AFF',
        'persons' => 1,
        'type' => 'AFILIACION INDIVIDUAL',
        'payment_frequency' => 'TRIMESTRAL',
        'next_payment_date' => '15/10/2026',
        'expiration_date' => '20/10/2026',
        'status' => 'POR PAGAR',
        'total_amount' => 100,
        ...$attributes,
    ]);
}

it('solo Administración y SUPERADMIN pueden ajustar cuotas', function (array $departments, bool $allowed): void {
    expect(CollectionAdjustmentAccess::userCan(adjusterTestUser($departments)))->toBe($allowed);
})->with([
    'administración' => [['ADMINISTRACION'], true],
    'superadmin' => [['SUPERADMIN'], true],
    'negocios' => [['NEGOCIOS'], false],
    'sin departamento' => [[], false],
]);

it('ajusta fecha y estado, deja las tres fechas iguales y registra el antes y después', function (): void {
    $collection = adjusterTestCollection();

    $adjustment = CollectionAdjuster::adjust($collection, [
        'date' => '2026-11-15',
        'status' => 'PAGADO',
        'reason' => 'Pago verificado en el estado de cuenta del cliente',
        'expected_updated_at' => (string) $collection->getRawOriginal('updated_at'),
    ], adjusterTestUser());

    $collection->refresh();

    expect($collection->next_payment_date)->toBe('15/11/2026')
        ->and($collection->expiration_date)->toBe('15/11/2026')
        ->and((string) $collection->filter_next_payment_date)->toStartWith('2026-11-15')
        ->and($collection->status)->toBe('PAGADO')
        ->and($adjustment->mode)->toBe(CollectionAdjustment::MODE_SINGLE)
        ->and($adjustment->performed_by_name)->toBe('Analista de prueba')
        ->and($adjustment->reason)->toBe('Pago verificado en el estado de cuenta del cliente')
        ->and($adjustment->changes)->toMatchArray([
            'next_payment_date' => ['before' => '15/10/2026', 'after' => '15/11/2026'],
            'expiration_date' => ['before' => '15/10/2026', 'after' => '15/11/2026'],
            'status' => ['before' => 'POR PAGAR', 'after' => 'PAGADO'],
        ]);
});

it('rechaza el ajuste y no toca la cuota cuando algo no cuadra', function (array $data, ?array $departments, string $message): void {
    $collection = adjusterTestCollection();

    expect(fn () => CollectionAdjuster::adjust($collection, [
        'date' => '2026-11-15',
        'status' => 'PAGADO',
        'reason' => 'Motivo suficientemente largo',
        ...$data,
    ], adjusterTestUser($departments ?? ['ADMINISTRACION'])))->toThrow(InvalidArgumentException::class, $message);

    expect($collection->fresh()->status)->toBe('POR PAGAR')
        ->and($collection->fresh()->next_payment_date)->toBe('15/10/2026')
        ->and(CollectionAdjustment::query()->where('collection_id', $collection->getKey())->exists())->toBeFalse();
})->with([
    'sin permiso' => [[], ['NEGOCIOS'], 'No tiene permiso'],
    'motivo corto' => [['reason' => 'corto'], null, 'motivo'],
    'estado inválido' => [['status' => 'CANCELADO'], null, 'estado válido'],
    'fecha inválida' => [['date' => '31/02/2026'], null, 'fecha de próximo pago válida'],
    'fecha muy lejana sin confirmar' => [['date' => '2062-11-15'], null, 'muy lejos de hoy'],
    'versión vieja (otra persona la cambió)' => [['expected_updated_at' => '2000-01-01 00:00:00'], null, 'Otra persona modificó'],
]);

it('acepta una fecha lejana si el usuario la confirma', function (): void {
    $collection = adjusterTestCollection();

    CollectionAdjuster::adjust($collection, [
        'date' => CarbonImmutable::today()->addYears(3)->toDateString(),
        'status' => 'POR PAGAR',
        'reason' => 'Contrato firmado a tres años vista',
        'confirm_unusual_date' => true,
    ], adjusterTestUser());

    expect($collection->fresh()->next_payment_date)->toBe(CarbonImmutable::today()->addYears(3)->format('d/m/Y'));
});

it('no guarda un ajuste sin cambios', function (): void {
    $collection = adjusterTestCollection(['expiration_date' => '15/10/2026']);

    expect(fn () => CollectionAdjuster::adjust($collection, [
        'date' => '2026-10-15',
        'status' => 'POR PAGAR',
        'reason' => 'Mismo valor, sin cambios reales',
    ], adjusterTestUser()))->toThrow(InvalidArgumentException::class, 'No hay cambios');
});

it('no deja revertir un pago que ya generó crédito de empresa aliada', function (): void {
    $collection = adjusterTestCollection(['status' => 'PAGADO']);
    CreditReconciliation::query()->forceCreate(['collection_id' => $collection->getKey(), 'total_to_pay' => 100]);

    expect(CollectionAdjuster::blockingReason($collection, 'POR PAGAR'))->toContain('crédito de empresa aliada')
        ->and(fn () => CollectionAdjuster::adjust($collection, [
            'date' => '2026-10-15',
            'status' => 'POR PAGAR',
            'reason' => 'Revertir pago registrado por error',
        ], adjusterTestUser()))->toThrow(InvalidArgumentException::class, 'crédito de empresa aliada')
        ->and($collection->fresh()->status)->toBe('PAGADO');
});

it('marca varias cuotas como pagadas en un solo lote con su bitácora', function (): void {
    $first = adjusterTestCollection();
    $second = adjusterTestCollection(['next_payment_date' => '15/01/2027']);

    $count = CollectionAdjuster::markManyAsPaid([$first, $second], 'Cuotas de 2024 verificadas con el cliente', adjusterTestUser());

    $adjustments = CollectionAdjustment::query()->whereIn('collection_id', [$first->getKey(), $second->getKey()])->get();

    expect($count)->toBe(2)
        ->and($first->fresh()->status)->toBe('PAGADO')
        ->and($second->fresh()->status)->toBe('PAGADO')
        ->and($adjustments)->toHaveCount(2)
        ->and($adjustments->pluck('mode')->unique()->all())->toBe([CollectionAdjustment::MODE_BULK])
        ->and($adjustments->pluck('batch_uuid')->unique())->toHaveCount(1);
});

it('el lote es todo o nada: si una cuota no está por pagar no marca ninguna', function (): void {
    $pending = adjusterTestCollection();
    $paid = adjusterTestCollection(['status' => 'PAGADO']);

    expect(fn () => CollectionAdjuster::markManyAsPaid([$pending, $paid], 'Lote mezclado con una ya pagada', adjusterTestUser()))
        ->toThrow(InvalidArgumentException::class, 'no está por pagar');

    expect($pending->fresh()->status)->toBe('POR PAGAR')
        ->and(CollectionAdjustment::query()->where('collection_id', $pending->getKey())->exists())->toBeFalse();
});

it('el lote tiene un máximo por vez', function (): void {
    $collections = collect(range(1, CollectionAdjuster::MAX_BULK + 1))
        ->map(fn (int $id): Collection => (new Collection)->forceFill(['id' => $id]));

    expect(fn () => CollectionAdjuster::markManyAsPaid($collections, 'Lote demasiado grande para una vez', adjusterTestUser()))
        ->toThrow(InvalidArgumentException::class, 'hasta '.CollectionAdjuster::MAX_BULK);
});

it('la vista previa muestra los cambios sin guardar nada', function (): void {
    $collection = adjusterTestCollection();

    expect(CollectionAdjuster::previewChanges($collection, '2026-11-15', 'PAGADO'))->toMatchArray([
        'next_payment_date' => ['before' => '15/10/2026', 'after' => '15/11/2026'],
        'status' => ['before' => 'POR PAGAR', 'after' => 'PAGADO'],
    ])
        ->and($collection->fresh()->next_payment_date)->toBe('15/10/2026');
});
