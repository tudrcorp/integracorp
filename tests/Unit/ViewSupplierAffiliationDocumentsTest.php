<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\Suppliers\Pages\ViewSupplier;

it('expone el método Livewire para eliminar un documento de afiliación por índice', function () {
    expect(method_exists(ViewSupplier::class, 'deleteSupplierAffiliationDocument'))->toBeTrue();
});

it('expone el método Livewire para eliminar la carta de aceptación', function () {
    expect(method_exists(ViewSupplier::class, 'deleteCartaAcceptance'))->toBeTrue();
});

it('normaliza rutas de documentos de afiliación a lista de strings', function () {
    $reflection = new ReflectionClass(ViewSupplier::class);
    $page = $reflection->newInstanceWithoutConstructor();
    $method = $reflection->getMethod('normalizeAffiliationDocumentPaths');
    $method->setAccessible(true);

    expect($method->invoke($page, null))->toBe([])
        ->and($method->invoke($page, ['  a.pdf ', 'b.pdf']))->toBe(['a.pdf', 'b.pdf'])
        ->and($method->invoke($page, 'solo.pdf'))->toBe(['solo.pdf']);
});
