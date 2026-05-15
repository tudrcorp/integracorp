<?php

declare(strict_types=1);

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\Collection as BillingCollectionRow;
use Illuminate\Support\Facades\View;

uses(Tests\TestCase::class);

it('la plantilla pdf de afiliación corporativa renderiza con datos mínimos', function (): void {
    $corp = new AffiliationCorporate([
        'id' => 1,
        'code' => 'CORP-01',
        'name_corporate' => 'Empresa Prueba',
        'rif' => '123456789',
        'status' => 'ACTIVA',
    ]);

    $affiliate = new AffiliateCorporate([
        'first_name' => 'Ana',
        'last_name' => 'Pérez',
        'nro_identificacion' => 'V123',
        'email' => 'ana@example.test',
    ]);

    $corp->setRelation('corporateAffiliates', collect([$affiliate]));
    $corp->setRelation('billingCollections', collect());
    $corp->setRelation('state', null);
    $corp->setRelation('city', null);
    $corp->setRelation('country', null);
    $corp->setRelation('region', null);
    $corp->setRelation('agent', null);
    $corp->setRelation('agency', null);

    $html = View::make('documents.affiliation-corporate-ficha', [
        'affiliationCorporate' => $corp,
    ])->render();

    expect($html)
        ->toContain('FICHA DE AFILIACIÓN CORPORATIVA')
        ->toContain('Empresa Prueba')
        ->toContain('Ana')
        ->toContain('J-123456789')
        ->toContain('Afiliados asociados (1)')
        ->toContain('Próximos pagos y estatus de cobranza')
        ->toContain('No hay cobranzas registradas para este código de afiliación.')
        ->not->toContain('<th>Apellido</th>');
});

it('la ficha corporativa lista cobranzas cuando existen registros', function (): void {
    $corp = new AffiliationCorporate([
        'id' => 2,
        'code' => 'CORP-ZZ',
        'name_corporate' => 'Otra Empresa',
        'rif' => '111',
        'status' => 'ACTIVA',
    ]);

    $corp->setRelation('corporateAffiliates', collect());
    $corp->setRelation('billingCollections', collect([
        new BillingCollectionRow([
            'expiration_date' => '2026-06-20',
            'status' => 'POR PAGAR',
            'payment_frequency' => 'ANUAL',
            'next_payment_date' => '2026-06-01',
            'total_amount' => 250.5,
        ]),
    ]));
    $corp->setRelation('state', null);
    $corp->setRelation('city', null);
    $corp->setRelation('country', null);
    $corp->setRelation('region', null);
    $corp->setRelation('agent', null);
    $corp->setRelation('agency', null);

    $html = View::make('documents.affiliation-corporate-ficha', [
        'affiliationCorporate' => $corp,
    ])->render();

    expect($html)
        ->toContain('Próximos pagos y estatus de cobranza')
        ->toContain('POR PAGAR')
        ->toContain('ANUAL')
        ->toContain('20/06/2026')
        ->toContain('US$ 250,50');
});

it('ordena cobranzas cronológicamente ascendente aunque vengan desordenadas', function (): void {
    $corp = new AffiliationCorporate([
        'id' => 9,
        'code' => 'CORP-SORT',
        'name_corporate' => 'Sort SA',
        'rif' => '1',
        'status' => 'ACTIVA',
    ]);

    $corp->setRelation('corporateAffiliates', collect());
    $corp->setRelation('billingCollections', collect([
        new BillingCollectionRow([
            'expiration_date' => '2026-12-01',
            'next_payment_date' => '2026-12-20',
            'status' => 'Z-ULTIMO',
            'payment_frequency' => 'ANUAL',
            'total_amount' => 400,
        ]),
        new BillingCollectionRow([
            'expiration_date' => '2026-02-01',
            'next_payment_date' => '2026-02-10',
            'status' => 'A-PRIMERO',
            'payment_frequency' => 'TRIMESTRAL',
            'total_amount' => 100,
        ]),
    ]));
    $corp->setRelation('state', null);
    $corp->setRelation('city', null);
    $corp->setRelation('country', null);
    $corp->setRelation('region', null);
    $corp->setRelation('agent', null);
    $corp->setRelation('agency', null);

    $html = View::make('documents.affiliation-corporate-ficha', [
        'affiliationCorporate' => $corp,
    ])->render();

    $posFirst = strpos($html, 'A-PRIMERO');
    $posLast = strpos($html, 'Z-ULTIMO');
    expect($posFirst)->not->toBeFalse()
        ->and($posLast)->not->toBeFalse()
        ->and($posFirst < $posLast)->toBeTrue();
});
