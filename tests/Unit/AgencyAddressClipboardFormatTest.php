<?php

declare(strict_types=1);

use App\Models\Agency;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use App\Support\Filament\CommercialStructure\AgencyAddressClipboardFormat;
use Tests\TestCase;

uses(TestCase::class);

it('formatea la direccion en venezuela para copiar al portapapeles', function (): void {
    $agency = new Agency([
        'name_representative' => 'María Teresa Bautista',
        'name_corporative' => 'MB Consultores',
        'address' => 'Av. El Prebo, Oficina 24',
    ]);

    $agency->setRelation('city', new City(['definition' => 'Valencia']));
    $agency->setRelation('state', new State(['definition' => 'Carabobo']));
    $agency->setRelation('country', new Country(['name' => 'Venezuela']));

    expect(AgencyAddressClipboardFormat::venezuela($agency))->toBe(
        "María Teresa Bautista\nAgencia MB Consultores\nDirección Av. El Prebo, Oficina 24\nCiudad Valencia, (Estado) Carabobo\nPaís - Venezuela"
    );
});

it('formatea la direccion internacional para copiar al portapapeles', function (): void {
    $agency = new Agency;
    $agency->forceFill([
        'name_representative' => 'Vivianne Castillo',
        'name_corporative' => 'Yv Solutions',
        'address_other_country' => '123 Main Street, Apt 4B',
        'city_other_country' => 'New York',
        'state_other_country' => 'NY',
        'postal_code_other_country' => '10001',
        'country_other_country' => 185,
    ]);

    $formatted = AgencyAddressClipboardFormat::international($agency);

    expect($formatted)
        ->toContain("Vivianne Castillo\nAgencia Yv Solutions")
        ->toContain('Dirección 123 Main Street, Apt 4B')
        ->toContain('Ciudad New York, (Estado) NY 10001')
        ->toMatch('/País – .+/');
});

it('expone botones de copia con formato de correspondencia en el infolist de agencia', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/AgencyInfolist.php');

    expect($source)
        ->toContain('AgencyAddressClipboardFormat::venezuela')
        ->toContain('AgencyAddressClipboardFormat::international')
        ->toContain("TextEntry::make('venezuela_address_copy')")
        ->toContain("TextEntry::make('international_address_copy')")
        ->toContain('Formato de correspondencia copiado');
});
