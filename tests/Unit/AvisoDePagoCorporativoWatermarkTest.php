<?php

declare(strict_types=1);

it('no incluye marca de agua y conserva el layout dompdf en aviso de pago corporativo', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/aviso-de-pago-corporativo.blade.php');

    expect($source)
        ->not->toContain('document-watermark')
        ->not->toContain('logoNewTDG.png')
        ->toContain('margin: 15mm 20mm')
        ->toContain('footer-legal')
        ->toContain('footer-banner')
        ->toContain('Recibo de Pago: Nro.')
        ->toContain('TOTAL DE AFILIADOS ASOCIADOS A LA AFILIACIÓN')
        ->toContain('affiliates_count')
        ->toContain('Documento: J-')
        ->toContain('sello-nuevo.png')
        ->toContain('table-layout: fixed')
        ->toContain('PERÍODO DE VIGENCIA DESDE EL');
});
