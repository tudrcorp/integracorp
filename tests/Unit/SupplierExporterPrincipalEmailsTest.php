<?php

declare(strict_types=1);

use App\Models\Supplier;
use App\Models\SupplierContactPrincipal;

test('correos de contactos principales se unen con punto y coma', function () {
    $supplier = new Supplier;
    $supplier->setRelation('supplierContactPrincipals', collect([
        new SupplierContactPrincipal(['email' => 'uno@example.com']),
        new SupplierContactPrincipal(['email' => 'dos@example.com']),
        new SupplierContactPrincipal(['email' => '  tres@example.com  ']),
        new SupplierContactPrincipal(['email' => 'uno@example.com']),
        new SupplierContactPrincipal(['email' => '']),
    ]));

    $result = $supplier->supplierContactPrincipals
        ->pluck('email')
        ->map(fn ($email) => is_string($email) ? trim($email) : '')
        ->filter(fn (string $email) => $email !== '')
        ->unique()
        ->values()
        ->implode('; ');

    expect($result)->toBe('uno@example.com; dos@example.com; tres@example.com');
});
