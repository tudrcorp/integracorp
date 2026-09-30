<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\AffiliateCorporates\AffiliateCorporateResource;
use App\Filament\Operations\Resources\Affiliates\AffiliateResource;
use App\Filament\Operations\Resources\CompanyAssociates\NuevosNegociosAssociateResource;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Company;
use App\Models\CompanyAssociate;
use App\Support\Filament\GlobalSearchAffiliateBusinessDetails;

uses(Tests\TestCase::class);

it('usa la unidad del afiliado y, si no la tiene, la de su afiliación', function (mixed $affiliate, mixed $affiliation, string $expected): void {
    expect(GlobalSearchAffiliateBusinessDetails::specificBusinessUnit($affiliate, $affiliation))->toBe($expected);
})->with([
    'del afiliado' => ['PQQ', 'OTRA', 'PQQ'],
    'respaldo de la afiliación' => [null, 'PQQ', 'PQQ'],
    'afiliado en blanco' => ['   ', 'PQQ', 'PQQ'],
    'sin dato' => [null, null, '—'],
    'valor no textual' => [['x'], null, '—'],
]);

it('muestra los proveedores separados por coma o un guion si no hay', function (mixed $providers, string $expected): void {
    expect(GlobalSearchAffiliateBusinessDetails::serviceProviders($providers))->toBe($expected);
})->with([
    'lista' => [['ATENMEDI', 'ILS'], 'ATENMEDI, ILS'],
    'texto json' => ['["ATENMEDI","TDEC"]', 'ATENMEDI, TDEC'],
    'vacío' => [[], '—'],
    'texto vacío' => ['', '—'],
    'nulo' => [null, '—'],
]);

it('agrega unidad y proveedores al resultado de afiliados individuales', function (): void {
    $affiliation = (new Affiliation)->forceFill(['specific_business_unit' => 'PQQ', 'service_providers' => ['ATENMEDI', 'ILS']]);
    $affiliate = (new Affiliate)->forceFill(['specific_business_unit' => null]);
    $affiliate->setRelation('affiliation', $affiliation);
    $affiliate->setRelation('plan', null);

    $details = AffiliateResource::getGlobalSearchResultDetails($affiliate);

    expect($details)
        ->toHaveKey('Unidad de Negocio Específica', 'PQQ')
        ->toHaveKey('Proveedor(es) de Servicios', 'ATENMEDI, ILS')
        ->and(array_search('Unidad de Negocio Específica', array_keys($details), true))
        ->toBe(array_search('Tipo de plan', array_keys($details), true) + 1);
});

it('agrega unidad y proveedores al resultado de afiliados corporativos', function (): void {
    $corporate = (new AffiliationCorporate)->forceFill(['specific_business_unit' => 'OTRA', 'service_providers' => ['TDEC']]);
    $affiliate = (new AffiliateCorporate)->forceFill(['specific_business_unit' => 'PQQ']);
    $affiliate->setRelation('affiliationCorporate', $corporate);
    $affiliate->setRelation('plan', null);

    expect(AffiliateCorporateResource::getGlobalSearchResultDetails($affiliate))
        ->toHaveKey('Unidad de Negocio Específica', 'PQQ')
        ->toHaveKey('Proveedor(es) de Servicios', 'TDEC');
});

it('agrega los proveedores de la empresa al resultado de Nuevos Negocios', function (): void {
    $associate = new CompanyAssociate;
    $associate->setRelation('company', (new Company)->forceFill(['name' => 'ZZ', 'service_providers' => ['ATENMEDI']]));

    expect(NuevosNegociosAssociateResource::getGlobalSearchResultDetails($associate))
        ->toHaveKey('Proveedor(es) de Servicios', 'ATENMEDI')
        ->not->toHaveKey('Unidad de Negocio Específica');
});
