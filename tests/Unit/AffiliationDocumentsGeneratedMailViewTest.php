<?php

declare(strict_types=1);

it('incluye firma y correos correctos en el mail de documentos de afiliación', function (): void {
    $path = dirname(__DIR__, 2).'/resources/views/mails/affiliationDocumentsGenerated.blade.php';
    $code = file_get_contents($path);

    expect($code)->not->toBeFalse();

    expect($code)
        ->toContain('📲 WhatsApp: (+58) 424 222 0056 / 424 227 1498')
        ->toContain('📩 Email: afiliaciones@tudrencasa.com')
        ->toContain('mailto:comercial@tudrencasa.com');
});
