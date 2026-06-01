<?php

declare(strict_types=1);

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\State;
use App\Support\Operations\OperationsMapSearchAddress;

it('concatena dirección de calle y ubicación administrativa para el mapa', function (): void {
    $affiliate = new Affiliate([
        'address' => 'AV OCTAVIO CAMEJO. URB CASA BOTE B N° 32',
        'region' => 'ORIENTE',
    ]);
    $affiliate->setRelation('country', new Country(['name' => 'VENEZUELA']));
    $affiliate->setRelation('state', new State(['definition' => 'ANZOATEGUI']));
    $affiliate->setRelation('city', new City(['definition' => 'LECHERIA']));

    expect(OperationsMapSearchAddress::forAffiliate($affiliate))
        ->toBe('AV OCTAVIO CAMEJO. URB CASA BOTE B N° 32, VENEZUELA · ANZOATEGUI · LECHERIA · ORIENTE');
});

it('devuelve solo calle o solo ubicación cuando falta el otro componente', function (): void {
    $onlyStreet = new Affiliate(['address' => 'Calle 1']);
    $onlyStreet->setRelation('country', null);
    $onlyStreet->setRelation('state', null);
    $onlyStreet->setRelation('city', null);

    expect(OperationsMapSearchAddress::forAffiliate($onlyStreet))->toBe('Calle 1');

    $onlyLocation = new Affiliate(['address' => null, 'region' => 'ORIENTE']);
    $onlyLocation->setRelation('country', new Country(['name' => 'VENEZUELA']));
    $onlyLocation->setRelation('state', null);
    $onlyLocation->setRelation('city', null);

    expect(OperationsMapSearchAddress::forAffiliate($onlyLocation))->toBe('VENEZUELA · ORIENTE');
});

it('concatena dirección de empresa corporativa para el mapa', function (): void {
    $corporate = new AffiliationCorporate([
        'address' => 'AV. PRINCIPAL EDIF. CENTRO PISO 5',
    ]);
    $corporate->setRelation('country', new Country(['name' => 'VENEZUELA']));
    $corporate->setRelation('state', new State(['definition' => 'DISTRITO CAPITAL']));
    $corporate->setRelation('city', new City(['definition' => 'CARACAS']));
    $corporate->setRelation('region', new Region(['definition' => 'CAPITAL']));

    expect(OperationsMapSearchAddress::forAffiliationCorporate($corporate))
        ->toBe('AV. PRINCIPAL EDIF. CENTRO PISO 5, VENEZUELA · DISTRITO CAPITAL · CARACAS · CAPITAL');
});

it('usa solo calle del afiliado corporativo cuando no hay ubicación en el registro', function (): void {
    $affiliate = new AffiliateCorporate(['address' => 'Urbanización Los Olivos']);

    expect(OperationsMapSearchAddress::forAffiliateCorporate($affiliate))->toBe('Urbanización Los Olivos');
});
