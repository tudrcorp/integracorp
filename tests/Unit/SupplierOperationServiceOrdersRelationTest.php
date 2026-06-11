<?php

declare(strict_types=1);

use App\Models\OperationServiceOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Relations\HasMany;

uses(Tests\TestCase::class);

it('define la relacion hasMany operationServiceOrders con supplier_id', function (): void {
    $relation = (new Supplier)->operationServiceOrders();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getForeignKeyName())->toBe('supplier_id')
        ->and($relation->getRelated())->toBeInstanceOf(OperationServiceOrder::class);
});

it('finalizedOperationServiceOrders incluye ordenes finalizadas canceladas y caducadas', function (): void {
    $relation = (new Supplier)->finalizedOperationServiceOrders();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getForeignKeyName())->toBe('supplier_id');

    $statusWhere = collect($relation->getQuery()->getQuery()->wheres)->first(
        fn (array $where): bool => ($where['type'] ?? '') === 'In'
            && (($where['column'] ?? '') === 'status' || ($where['column'] ?? '') === 'operation_service_orders.status')
    );

    expect($statusWhere)->not->toBeNull()
        ->and($statusWhere['values'] ?? [])->toContain('FINALIZADO', 'CANCELADA', 'CANCELADO', 'CADUCADA');
});
