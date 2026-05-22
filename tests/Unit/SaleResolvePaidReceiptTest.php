<?php

declare(strict_types=1);

use App\Models\Sale;

it('resuelve tabla paid_memberships para ventas individuales', function (): void {
    $sale = new Sale(['type' => 'AFILIACION INDIVIDUAL']);

    expect($sale->paidReceiptTableName())->toBe('paid_memberships');
});

it('resuelve tabla paid_membership_corporates para ventas corporativas', function (): void {
    $sale = new Sale(['type' => 'AFILIACION CORPORATIVA']);

    expect($sale->paidReceiptTableName())->toBe('paid_membership_corporates');
});
