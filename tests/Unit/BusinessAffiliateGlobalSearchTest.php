<?php

declare(strict_types=1);

use App\Filament\Business\Resources\AffiliateCorporates\AffiliateCorporateResource;
use App\Filament\Business\Resources\Affiliates\AffiliateResource;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;

it('expone la cedula de afiliados individuales en la busqueda global de negocios', function (): void {
    expect(AffiliateResource::shouldRegisterNavigation())->toBeFalse()
        ->and(AffiliateResource::canCreate())->toBeFalse()
        ->and(AffiliateResource::getGloballySearchableAttributes())
        ->toContain('full_name')
        ->toContain('nro_identificacion')
        ->and(DepartmentNavigationPermissionRegistry::slugsFor(AffiliateResource::class))
        ->toBe(['afiliaciones-individuales']);
});

it('expone la cedula de afiliados corporativos en la busqueda global de negocios', function (): void {
    expect(AffiliateCorporateResource::shouldRegisterNavigation())->toBeFalse()
        ->and(AffiliateCorporateResource::canCreate())->toBeFalse()
        ->and(AffiliateCorporateResource::getGloballySearchableAttributes())
        ->toContain('first_name')
        ->toContain('last_name')
        ->toContain('nro_identificacion')
        ->and(DepartmentNavigationPermissionRegistry::slugsFor(AffiliateCorporateResource::class))
        ->toBe(['afiliaciones-corporativas']);
});

it('detalla el afiliado individual encontrado por cedula', function (): void {
    $affiliate = new Affiliate([
        'full_name' => 'Ana Perez',
        'nro_identificacion' => 'V-12345678',
        'relationship' => 'TITULAR',
        'status' => 'ACTIVO',
    ]);
    $affiliate->setRelation('affiliation', new Affiliation(['code' => 'AFF-100']));

    expect(AffiliateResource::getGlobalSearchResultTitle($affiliate))->toBe('Ana Perez')
        ->and(AffiliateResource::getGlobalSearchResultDetails($affiliate))->toMatchArray([
            'Cédula' => 'V-12345678',
            'Afiliación' => 'AFF-100',
            'Parentesco' => 'TITULAR',
            'Estatus' => 'ACTIVO',
        ])
        ->and(AffiliateResource::getGlobalSearchResultUrl(new Affiliate(['full_name' => 'Huérfano'])))->toBeNull();
});

it('detalla el afiliado corporativo encontrado por cedula', function (): void {
    $affiliate = new AffiliateCorporate([
        'first_name' => 'Luis',
        'last_name' => 'Gomez',
        'nro_identificacion' => 'V-87654321',
        'status' => 'ACTIVO',
    ]);
    $affiliate->setRelation('affiliationCorporate', new AffiliationCorporate([
        'code' => 'CORP-200',
        'name_corporate' => 'Acme Salud',
    ]));

    expect(AffiliateCorporateResource::getGlobalSearchResultTitle($affiliate))->toBe('Luis Gomez')
        ->and(AffiliateCorporateResource::getGlobalSearchResultDetails($affiliate))->toMatchArray([
            'Cédula' => 'V-87654321',
            'Empresa' => 'Acme Salud',
            'Afiliación' => 'CORP-200',
            'Estatus' => 'ACTIVO',
        ])
        ->and(AffiliateCorporateResource::getGlobalSearchResultUrl(new AffiliateCorporate([
            'first_name' => 'Huérfano',
        ])))->toBeNull();
});

it('reutiliza el permiso de la afiliacion y apunta a la ficha padre', function (): void {
    $individual = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliates/AffiliateResource.php');
    $corporate = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliateCorporates/AffiliateCorporateResource.php');

    expect($individual)->not->toBeFalse()
        ->toContain('AffiliationResource::getUrl')
        ->toContain("'nro_identificacion'")
        ->toContain('shouldRegisterNavigation')
        ->and($corporate)->not->toBeFalse()
        ->toContain('AffiliationCorporateResource::getUrl')
        ->toContain("'nro_identificacion'")
        ->toContain('shouldRegisterNavigation');
});
