<?php

declare(strict_types=1);

use App\Models\TravelAgency;
use App\Services\TravelAgencyFichaPdfService;

it('define ruta de almacenamiento whatsapp para ficha de agencia de viajes', function (): void {
    $travelAgency = new TravelAgency;
    $travelAgency->id = 42;
    $travelAgency->numberIdentification = 'J-12345';

    expect(TravelAgencyFichaPdfService::whatsappStorageRelativePath($travelAgency))
        ->toBe('business-fichas/travel-agencies/ficha-agencia-viajes-J-12345.pdf');
});

it('genera caption de whatsapp para ficha de agencia de viajes', function (): void {
    $travelAgency = new TravelAgency;
    $travelAgency->id = 7;
    $travelAgency->name = 'Viajes Caribe';
    $travelAgency->numberIdentification = 'V-999';

    $caption = TravelAgencyFichaPdfService::whatsappCaption($travelAgency);

    expect($caption)
        ->toContain('Viajes Caribe')
        ->toContain('J/V/E-V-999')
        ->toContain('Ficha de agencia de viajes');
});

it('job whatsapp de agencia de viajes persiste pdf antes de enviar', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendBusinessTravelAgencyFichaPdfWhatsAppJob.php');

    expect($source)
        ->toContain('TravelAgencyFichaPdfService::persistForWhatsApp')
        ->toContain('NotificationController::sendWhatsAppDocument');
});
