<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

use App\Filament\Business\Resources\TravelAgencies\Schemas\TravelAgencyForm;
use App\Filament\Business\Resources\TravelAgencies\Schemas\TravelAgencyInfolist;
use Filament\Schemas\Schema;

it('configura el formulario de agencia de viajes business sin error', function (): void {
    $schema = Schema::make();
    $configured = TravelAgencyForm::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('formulario agencia de viajes usa pestañas con estilos del sistema', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/TravelAgencies/Schemas/TravelAgencyForm.php');

    expect($source)
        ->toContain("Tabs::make('travelAgencyFormTabs')")
        ->toContain('persistTab')
        ->toContain('TABS_CONTAINER')
        ->toContain('IOS_SECTION_CLASS')
        ->toContain('IOS_INNER_CLASS')
        ->toContain("Tab::make('Marca')")
        ->toContain("Tab::make('Información general')")
        ->toContain("Tab::make('Contactos')")
        ->toContain("Tab::make('Jerarquía')")
        ->toContain("Tab::make('Bancos nacionales')")
        ->toContain("Tab::make('Bancos extranjeros')")
        ->toContain("Tab::make('Observaciones')");
});

it('configura el infolist de agencia de viajes business sin error', function (): void {
    $schema = Schema::make();
    $configured = TravelAgencyInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('infolist agencia de viajes usa pestañas con estilos del sistema', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/TravelAgencies/Schemas/TravelAgencyInfolist.php');

    expect($source)
        ->toContain("Tabs::make('travelAgencyInfolistTabs')")
        ->toContain('persistTab')
        ->toContain('TABS_CONTAINER')
        ->toContain('IOS_SECTION_CLASS')
        ->toContain('IOS_INNER_CLASS')
        ->toContain("Tab::make('Marca')")
        ->toContain("Tab::make('Información general')")
        ->toContain("Tab::make('Contactos')")
        ->toContain("Tab::make('Jerarquía')")
        ->toContain("Tab::make('Bancos nacionales')")
        ->toContain("Tab::make('Bancos extranjeros')")
        ->toContain("Tab::make('Observaciones')")
        ->toContain("Tab::make('Auditoría')");
});

it('recurso business registra pagina view de agencia de viajes', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/TravelAgencies/TravelAgencyResource.php');

    expect($source)
        ->toContain('TravelAgencyInfolist::configure')
        ->toContain('ViewTravelAgency::route');
});

it('pagina ver agencia de viajes usa botones con estilo iOS', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/TravelAgencies/Pages/ViewTravelAgency.php');

    expect($source)
        ->toContain('IOS_GRAY_BUTTON_CLASS')
        ->toContain('IOS_PRIMARY_BUTTON_CLASS')
        ->toContain('ticket-btn-ios-gray')
        ->toContain('aviso-btn-ios-primary')
        ->toContain('badgeStyleForStatus');
});

it('pagina ver agencia de viajes incluye accion de ficha pdf con vista previa y envios', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/TravelAgencies/Pages/ViewTravelAgency.php');

    expect($source)
        ->toContain('travelAgencyFichaPreview')
        ->toContain('Ficha PDF')
        ->toContain('travel-agency-ficha-panel')
        ->toContain('QueuesTravelAgencyFichaPdfSharing')
        ->toContain('BusinessTravelAgencyFichaPdfAccess::userCanAccess')
        ->toContain('IOS_SUCCESS_BUTTON_CLASS');
});

it('panel de ficha de agencia de viajes expone correo y whatsapp', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/travel-agencies/travel-agency-ficha-panel.blade.php');

    expect($source)
        ->toContain('business.travel-agencies.ficha-pdf.preview')
        ->toContain('queueTravelAgencyFichaPdfEmail')
        ->toContain('queueTravelAgencyFichaPdfWhatsApp')
        ->toContain('Enviar por correo')
        ->toContain('Enviar por WhatsApp')
        ->toContain('Enviar WhatsApp');
});
