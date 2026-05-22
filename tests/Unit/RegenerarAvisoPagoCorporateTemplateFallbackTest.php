<?php

declare(strict_types=1);

it('usa fallback para subtotal semestral en aviso de pago corporativo regenerado', function (): void {
    $path = dirname(__DIR__, 2).'/resources/views/documents/regenerar-aviso-de-pago-corporativo.blade.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("data_get(\$planRow, 'subtotal_semestral', \$subtotalQuarterly)")
        ->and($contents)->toContain("\$planRow = \$data['plan'][\$i];");
});

it('incluye marca de agua y layout dompdf en regenerar aviso de pago corporativo', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/regenerar-aviso-de-pago-corporativo.blade.php');

    expect($source)
        ->toContain('document-watermark')
        ->toContain("public_path('image/logoNewTDG.png')")
        ->toContain('margin: 15mm 20mm')
        ->toContain('footer-legal')
        ->toContain('footer-banner')
        ->toContain('stamp-section')
        ->toContain('PERÍODO DE VIGENCIA DESDE EL');
});
