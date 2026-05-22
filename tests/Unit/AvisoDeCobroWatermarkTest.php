<?php

declare(strict_types=1);

it('incluye marca de agua y layout dompdf en aviso de cobro individual', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/aviso-de-cobro.blade.php');

    expect($source)
        ->toContain('document-watermark')
        ->toContain("public_path('image/logoNewTDG.png')")
        ->toContain('width: 85%')
        ->toContain('margin: 15mm 20mm')
        ->toContain('footer-legal')
        ->toContain('bottom: 30mm')
        ->toContain('text-align: right')
        ->toContain('footer-banner')
        ->toContain('Documento: V-')
        ->toContain('table-layout: fixed');
});
