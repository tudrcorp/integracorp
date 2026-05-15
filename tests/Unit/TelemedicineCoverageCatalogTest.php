<?php

declare(strict_types=1);

use App\Support\Telemedicine\TelemedicineCoverageCatalog;

it('trata CUBIERTO como cubierto y NO CUBIERTO como no cubierto', function (): void {
    expect(TelemedicineCoverageCatalog::itemIsCoveredFromCatalogType('CUBIERTO'))->toBeTrue()
        ->and(TelemedicineCoverageCatalog::itemIsCoveredFromCatalogType('NO CUBIERTO'))->toBeFalse()
        ->and(TelemedicineCoverageCatalog::itemIsCoveredFromCatalogType(null))->toBeTrue()
        ->and(TelemedicineCoverageCatalog::itemIsCoveredFromCatalogType(''))->toBeTrue();
});
