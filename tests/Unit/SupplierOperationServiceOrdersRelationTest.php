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

it('finalizedOperationServiceOrders solo incluye estatus FINALIZADO', function (): void {
    $relation = (new Supplier)->finalizedOperationServiceOrders();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getForeignKeyName())->toBe('supplier_id');

    $statusWhere = collect($relation->getQuery()->getQuery()->wheres)->first(
        fn (array $where): bool => ($where['type'] ?? '') === 'Basic'
            && (($where['column'] ?? '') === 'status' || ($where['column'] ?? '') === 'operation_service_orders.status')
            && ($where['value'] ?? null) === 'FINALIZADO'
    );

    expect($statusWhere)->not->toBeNull();
});
