<?php

declare(strict_types=1);

use App\Models\OperationServiceOrder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

test('operation service order fillable includes operation_inventory_ubication_id', function () {
    expect((new OperationServiceOrder)->getFillable())->toContain('operation_inventory_ubication_id');
});

test('operation service order fillable includes total_items and total_items_unit', function () {
    $fillable = (new OperationServiceOrder)->getFillable();

    expect($fillable)->toContain('total_items')->and($fillable)->toContain('total_items_unit');
});

test('operation service order has operationInventoryUbication relation', function () {
    expect((new OperationServiceOrder)->operationInventoryUbication())->toBeInstanceOf(BelongsTo::class);
});
