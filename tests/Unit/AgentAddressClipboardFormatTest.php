<?php

declare(strict_types=1);

use App\Models\Agency;
use App\Models\Agent;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use App\Support\Filament\CommercialStructure\AgentAddressClipboardFormat;
use Tests\TestCase;

uses(TestCase::class);

it('formatea la direccion en venezuela del agente para copiar al portapapeles', function (): void {
    $agent = new Agent([
        'name' => 'María Teresa Bautista',
        'address' => 'Av. El Prebo, Oficina 24',
    ]);

    $agent->setRelation('agency', new Agency(['name_corporative' => 'MB Consultores']));
    $agent->setRelation('city', new City(['definition' => 'Valencia']));
    $agent->setRelation('state', new State(['definition' => 'Carabobo']));
    $agent->setRelation('country', new Country(['name' => 'Venezuela']));

    expect(AgentAddressClipboardFormat::venezuela($agent))->toBe(
        "María Teresa Bautista\nAgencia MB Consultores\nDirección Av. El Prebo, Oficina 24\nCiudad Valencia, (Estado) Carabobo\nPaís - Venezuela"
    );
});

it('formatea la direccion internacional del agente para copiar al portapapeles', function (): void {
    $agent = new Agent;
    $agent->forceFill([
        'name' => 'Vivianne Castillo',
        'address_other_country' => '123 Main Street, Apt 4B',
        'city_other_country' => 'New York',
        'state_other_country' => 'NY',
        'postal_code_other_country' => '10001',
        'country_other_country' => 185,
    ]);
    $agent->setRelation('agency', new Agency(['name_corporative' => 'Yv Solutions']));

    $formatted = AgentAddressClipboardFormat::international($agent);

    expect($formatted)
        ->toContain("Vivianne Castillo\nAgencia Yv Solutions")
        ->toContain('Dirección 123 Main Street, Apt 4B')
        ->toContain('Ciudad New York, (Estado) NY 10001')
        ->toMatch('/País – .+/');
});

it('expone tarjetas de direccion y botones de copia en el infolist compartido de agentes', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/AgentInfolist.php');

    expect($source)
        ->toContain('AgentAddressClipboardFormat::venezuela')
        ->toContain('AgentAddressClipboardFormat::international')
        ->toContain("Text::make('Dirección en Venezuela')")
        ->toContain("Text::make('Dirección en Otros Paises')")
        ->toContain('IOS_ADDRESS_VENEZUELA_CARD')
        ->toContain("TextEntry::make('venezuela_address_copy')")
        ->toContain("TextEntry::make('international_address_copy')");
});
