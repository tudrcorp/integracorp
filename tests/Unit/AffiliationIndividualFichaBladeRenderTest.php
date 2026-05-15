<?php

declare(strict_types=1);

use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\Collection as BillingCollectionRow;
use Illuminate\Support\Facades\View;

uses(Tests\TestCase::class);

it('la plantilla pdf de afiliación individual renderiza con datos mínimos', function (): void {
    $aff = new Affiliation([
        'id' => 1,
        'code' => 'IND-99',
        'full_name_ti' => 'Titular Demo',
        'nro_identificacion_ti' => 'V999',
        'status' => 'ACTIVA',
    ]);

    $member = new Affiliate([
        'full_name' => 'Familiar Uno',
        'nro_identificacion' => 'V888',
        'relationship' => 'HIJO',
    ]);

    $aff->setRelation('affiliates', collect([$member]));
    $aff->setRelation('billingCollections', collect());
    $aff->setRelation('plan', null);
    $aff->setRelation('coverage', null);
    $aff->setRelation('state', null);
    $aff->setRelation('city', null);
    $aff->setRelation('country', null);
    $aff->setRelation('agent', null);
    $aff->setRelation('agency', null);

    $html = View::make('documents.affiliation-individual-ficha', [
        'affiliation' => $aff,
    ])->render();

    expect($html)
        ->toContain('FICHA DE AFILIACIÓN INDIVIDUAL')
        ->toContain('Titular Demo')
        ->toContain('Familiar Uno')
        ->toContain('Familiares afiliados (1)')
        ->toContain('Próximos pagos y estatus de cobranza')
        ->toContain('No hay cobranzas registradas para este código de afiliación.');
});

it('la ficha individual lista cobranzas cuando existen registros', function (): void {
    $aff = new Affiliation([
        'id' => 3,
        'code' => 'IND-COB',
        'full_name_ti' => 'Titular Cob',
        'status' => 'ACTIVA',
    ]);

    $aff->setRelation('affiliates', collect());
    $aff->setRelation('billingCollections', collect([
        new BillingCollectionRow([
            'expiration_date' => '2026-01-10',
            'status' => 'PAGADO',
            'payment_frequency' => 'TRIMESTRAL',
            'next_payment_date' => null,
            'total_amount' => 100,
        ]),
    ]));
    $aff->setRelation('plan', null);
    $aff->setRelation('coverage', null);
    $aff->setRelation('state', null);
    $aff->setRelation('city', null);
    $aff->setRelation('country', null);
    $aff->setRelation('agent', null);
    $aff->setRelation('agency', null);

    $html = View::make('documents.affiliation-individual-ficha', [
        'affiliation' => $aff,
    ])->render();

    expect($html)
        ->toContain('Próximos pagos y estatus de cobranza')
        ->toContain('PAGADO')
        ->toContain('TRIMESTRAL')
        ->toContain('10/01/2026')
        ->toContain('US$ 100,00');
});

it('ordena cobranzas cronológicamente ascendente aunque vengan desordenadas', function (): void {
    $aff = new Affiliation([
        'id' => 8,
        'code' => 'IND-SORT',
        'full_name_ti' => 'Titular Sort',
        'status' => 'ACTIVA',
    ]);

    $aff->setRelation('affiliates', collect());
    $aff->setRelation('billingCollections', collect([
        new BillingCollectionRow([
            'expiration_date' => '2026-11-01',
            'next_payment_date' => '2026-11-30',
            'status' => 'Z-ULTIMO',
            'payment_frequency' => 'ANUAL',
            'total_amount' => 300,
        ]),
        new BillingCollectionRow([
            'expiration_date' => '2026-04-01',
            'next_payment_date' => '2026-04-05',
            'status' => 'A-PRIMERO',
            'payment_frequency' => 'MENSUAL',
            'total_amount' => 50,
        ]),
    ]));
    $aff->setRelation('plan', null);
    $aff->setRelation('coverage', null);
    $aff->setRelation('state', null);
    $aff->setRelation('city', null);
    $aff->setRelation('country', null);
    $aff->setRelation('agent', null);
    $aff->setRelation('agency', null);

    $html = View::make('documents.affiliation-individual-ficha', [
        'affiliation' => $aff,
    ])->render();

    $posFirst = strpos($html, 'A-PRIMERO');
    $posLast = strpos($html, 'Z-ULTIMO');
    expect($posFirst)->not->toBeFalse()
        ->and($posLast)->not->toBeFalse()
        ->and($posFirst < $posLast)->toBeTrue();
});
