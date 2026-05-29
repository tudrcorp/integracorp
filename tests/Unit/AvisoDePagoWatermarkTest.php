<?php

declare(strict_types=1);

it('incluye marca de agua y layout dompdf en aviso de pago', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/aviso-de-pago.blade.php');

    expect($source)
        ->toContain('document-watermark')
        ->toContain("public_path('image/logoNewTDG.png')")
        ->toContain('width: 85%')
        ->toContain('margin: 15mm 20mm')
        ->toContain('footer-legal')
        ->toContain('footer-banner')
        ->toContain('Recibo de Pago: Nro.')
        ->toContain('TOTAL DE AFILIADOS ASOCIADOS A LA AFILIACIÓN')
        ->toContain('affiliates_count')
        ->toContain('sello-nuevo.png')
        ->toContain('table-layout: fixed');
});

it('incluye marca de agua y layout dompdf en regenerar aviso de pago', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/regenerar-aviso-de-pago.blade.php');

    expect($source)
        ->toContain('document-watermark')
        ->toContain("public_path('image/logoNewTDG.png')")
        ->toContain('footer-legal')
        ->toContain('PERÍODO DE VIGENCIA DESDE EL');
});

it('aviso de pago y regenerar aviso de pago comparten el mismo layout', function (): void {
    $base = dirname(__DIR__, 2).'/resources/views/documents/';
    $aviso = file_get_contents($base.'aviso-de-pago.blade.php');
    $regenerar = file_get_contents($base.'regenerar-aviso-de-pago.blade.php');

    expect(md5($aviso))->toBe(md5($regenerar));
});
