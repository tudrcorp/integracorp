<?php

declare(strict_types=1);

it('el correo de aviso de pago usa la misma estructura e imagen de header que la carta de bienvenida agente', function (): void {
    $carta = file_get_contents(dirname(__DIR__, 2).'/resources/views/mails/carta-bienvenida-agente.blade.php');
    $aviso = file_get_contents(dirname(__DIR__, 2).'/resources/views/mails/aviso-de-pago.blade.php');

    expect($aviso)
        ->toContain("public_path('image/logoNewPdf.png')")
        ->toContain('$message->embed($logoPath)')
        ->toContain("asset('image/logoNewPdf.png')")
        ->toContain('{{ $logoSrc }}')
        ->toContain('max-width:620px')
        ->toContain('Gracias por confiar en nosotros para gestionar las necesidades médicas de tu empresa.')
        ->not->toContain('app.piedy.com')
        ->not->toContain('bannerHeader.png');

    expect($carta)->toContain('logoNewPdf.png');
});

it('soporta mostrar el numero de recibo en el titulo del correo', function (): void {
    $aviso = file_get_contents(dirname(__DIR__, 2).'/resources/views/mails/aviso-de-pago.blade.php');

    expect($aviso)
        ->toContain('$invoiceNumber')
        ->toContain('Recibo de pago Nro.');
});
