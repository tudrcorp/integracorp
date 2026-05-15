<?php

declare(strict_types=1);

use App\Models\AffiliationCorporate;
use App\Services\AffiliationCorporateFichaPdfService;

uses(Tests\TestCase::class);

it('nombre de archivo de descarga de ficha corporativa sigue el patrón esperado', function (): void {
    $record = new AffiliationCorporate;
    $record->id = 12;

    expect(AffiliationCorporateFichaPdfService::downloadFilename($record))->toBe('Ficha-Afiliacion-Corporativa-12.pdf');
});
