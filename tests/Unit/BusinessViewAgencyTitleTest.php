<?php

declare(strict_types=1);

it('muestra información principal en el título de vista de agencia business', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Pages/ViewAgency.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('public function getTitle(): string|Htmlable')
        ->toContain('Agencia: ')
        ->toContain('name_corporative')
        ->toContain('badgeStyleForStatus')
        ->toContain('email')
        ->toContain('phone');
});

it('incluye acción de ficha pdf con vista previa y envío en view agency business', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Pages/ViewAgency.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('agencyFichaPreview')
        ->toContain('Ficha PDF')
        ->toContain('agency-ficha-panel')
        ->toContain('QueuesAgencyFichaPdfEmail')
        ->toContain('BusinessAgencyFichaPdfAccess::userCanAccess');
});

it('expone panel reutilizable de ficha con correo y whatsapp', function (): void {
    $path = dirname(__DIR__, 2).'/resources/views/filament/business/agencies/agency-ficha-panel.blade.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('business.agencies.ficha-pdf.preview')
        ->toContain('queueAgencyFichaPdfEmail')
        ->toContain('queueAgencyFichaPdfWhatsApp')
        ->toContain('Enviar por correo')
        ->toContain('Enviar por WhatsApp')
        ->toContain('Enviar WhatsApp');
});
