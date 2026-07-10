<?php

declare(strict_types=1);

use App\Support\QrCode\GdPngQrCodeGenerator;

uses(Tests\TestCase::class);

it('genera un png valido usando gd sin depender de imagick', function () {
    if (! extension_loaded('gd')) {
        $this->markTestSkipped('La extensión GD no está disponible en este entorno.');
    }

    $png = GdPngQrCodeGenerator::generate(
        content: 'https://example.test/storage/condicionados/CondicionesIDEAL.pdf',
        size: 120,
        errorCorrection: 'M',
        margin: 0,
    );

    expect($png)->toStartWith("\x89PNG\r\n\x1a\n")
        ->and(strlen($png))->toBeGreaterThan(100);
});
