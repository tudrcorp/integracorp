<?php

declare(strict_types=1);

use App\Support\PdfCertifiedCheckBadge;

uses(Tests\TestCase::class);

it('genera un badge circular en PNG para PDF cuando GD está disponible', function (): void {
    if (! extension_loaded('gd')) {
        $this->markTestSkipped('La extensión GD no está disponible.');
    }

    $dataUri = PdfCertifiedCheckBadge::dataUri();

    expect($dataUri)
        ->toStartWith('data:image/png;base64,')
        ->and(strlen($dataUri))->toBeGreaterThan(100);
});
