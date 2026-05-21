<?php

declare(strict_types=1);

use App\Support\Telemedicine\TelemedicineCoverageCatalog;

it('trata CUBIERTO como cubierto y NO CUBIERTO como no cubierto', function (): void {
    expect(TelemedicineCoverageCatalog::itemIsCoveredFromCatalogType('CUBIERTO'))->toBeTrue()
        ->and(TelemedicineCoverageCatalog::itemIsCoveredFromCatalogType('NO CUBIERTO'))->toBeFalse()
        ->and(TelemedicineCoverageCatalog::itemIsCoveredFromCatalogType(null))->toBeTrue()
        ->and(TelemedicineCoverageCatalog::itemIsCoveredFromCatalogType(''))->toBeTrue();
});

it('normaliza nombres y prioriza tipo CUBIERTO en resolución interna', function (): void {
    $reflection = new ReflectionClass(TelemedicineCoverageCatalog::class);
    $normalizeKey = $reflection->getMethod('normalizeKey');
    $normalizeKey->setAccessible(true);
    $resolveTypeWithPriority = $reflection->getMethod('resolveTypeWithPriority');
    $resolveTypeWithPriority->setAccessible(true);

    $normalizedUpper = $normalizeKey->invoke(null, 'CREATININA');
    $normalizedAccented = $normalizeKey->invoke(null, '  créatínina  ');
    $resolvedType = $resolveTypeWithPriority->invoke(null, 'NO CUBIERTO', 'CUBIERTO');

    expect($normalizedUpper)->toBe($normalizedAccented)
        ->and($resolvedType)->toBe('CUBIERTO');
});
