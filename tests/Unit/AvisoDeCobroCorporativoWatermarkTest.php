<?php

declare(strict_types=1);

it('incluye marca de agua con logo al 85% en aviso de cobro corporativo', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/aviso-de-cobro-corporativo.blade.php');

    expect($source)
        ->toContain('document-watermark')
        ->toContain("public_path('image/logoNewTDG.png')")
        ->toContain('width: 85%')
        ->toContain('margin: 15mm 20mm')
        ->toContain('footer-legal')
        ->toContain('position: fixed')
        ->toContain('bottom: 30mm')
        ->toContain('text-align: right')
        ->toContain('footer-banner')
        ->toContain('TOTAL DE AFILIADOS ASOCIADOS A LA AFILIACIÓN')
        ->toContain('affiliates_count')
        ->toContain('table-layout: fixed');
});
