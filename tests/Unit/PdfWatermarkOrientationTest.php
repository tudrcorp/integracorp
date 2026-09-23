<?php

declare(strict_types=1);

/**
 * La marca de agua va derecha (horizontal), centrada donde siempre estuvo:
 * solo se quitó la rotación, no la posición ni el tamaño.
 */
it('la marca de agua de los documentos no está rotada', function (string $template): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/'.$template.'.blade.php');

    expect($source)
        ->toMatch('/\.watermark\s*\{[^}]*transform: translateY\(-50%\);/s')
        ->not->toMatch('/\.watermark\s*\{[^}]*rotate\(/s');
})->with([
    'informe médico y seguimiento' => 'partials/informe-medico-homologado',
    'récipe' => 'partials/telemedicine-recipe-homologado',
    'órdenes y referencia a especialista' => 'partials/telemedicine-orden-homologada',
    'bitácora del caso' => 'bitacora-caso',
    'orden de servicio' => 'operation-service-order-pdf',
]);
