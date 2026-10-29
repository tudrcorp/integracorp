<?php

declare(strict_types=1);

it('no incluye marca de agua y conserva el layout dompdf en aviso de cobro individual', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/aviso-de-cobro.blade.php');

    expect($source)
        ->not->toContain('document-watermark')
        ->not->toContain('logoNewTDG.png')
        ->toContain('margin: 15mm 20mm')
        ->toContain('footer-legal')
        ->toContain('bottom: 30mm')
        ->toContain('text-align: right')
        ->toContain('footer-banner')
        ->toContain('Documento: V-')
        ->toContain('TOTAL DE AFILIADOS ASOCIADOS A LA AFILIACIÓN')
        ->toContain('affiliates_count')
        ->toContain('table-layout: fixed');
});
