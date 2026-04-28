<?php

declare(strict_types=1);

it('usa fallback para subtotal semestral en aviso de pago corporativo regenerado', function (): void {
    $path = dirname(__DIR__, 2).'/resources/views/documents/regenerar-aviso-de-pago-corporativo.blade.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("data_get(\$planRow, 'subtotal_semestral', \$subtotalQuarterly)")
        ->and($contents)->toContain("\$planRow = \$data['plan'][\$i];");
});
