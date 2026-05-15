<?php

declare(strict_types=1);

use App\Models\Affiliation;
use App\Services\AffiliationFichaPdfService;

uses(Tests\TestCase::class);

it('nombre de archivo de descarga de ficha individual sigue el patrón esperado', function (): void {
    $record = new Affiliation;
    $record->id = 5;

    expect(AffiliationFichaPdfService::downloadFilename($record))->toBe('Ficha-Afiliacion-Individual-5.pdf');
});
