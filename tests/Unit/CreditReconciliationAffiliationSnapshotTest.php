<?php

declare(strict_types=1);

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\PaidMembership;
use App\Models\Plan;
use App\Support\CreditReconciliations\CreditReconciliationAffiliationSnapshot;
use App\Support\CreditReconciliations\WhiteCompanyCreditMovementRecorder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

it('arma el snapshot de una afiliacion individual', function (): void {
    $affiliation = new Affiliation;
    $affiliation->forceFill([
        'id' => 11,
        'code' => 'AFF-100',
        'full_name_ti' => 'Ana Titular',
        'nro_identificacion_ti' => 'V-123',
        'full_name_payer' => 'Luis Pagador',
        'status' => 'ACTIVA',
        'code_agency' => 'TDG-101',
        'fee_anual' => 1200,
        'total_amount' => 300,
        'payment_frequency' => 'TRIMESTRAL',
        'plan_id' => 7,
        'family_members' => 1,
    ]);
    $affiliation->setRelation('plan', (new Plan)->forceFill([
        'description' => 'Plan Integral',
        'type' => 'BASICO',
    ]));
    $affiliation->setRelation('affiliates', new EloquentCollection([
        (object) ['id' => 1],
        (object) ['id' => 2],
        (object) ['id' => 3],
    ]));

    $snapshot = CreditReconciliationAffiliationSnapshot::fromIndividual($affiliation);

    expect($snapshot['affiliation_kind'])->toBe('individual')
        ->and($snapshot['affiliation_id'])->toBe(11)
        ->and($snapshot['affiliation_corporate_id'])->toBeNull()
        ->and($snapshot['affiliation_code'])->toBe('AFF-100')
        ->and($snapshot['affiliation_information'])->toContain('Código: AFF-100')
        ->and($snapshot['affiliation_information'])->toContain('Titular: Ana Titular (V-123)')
        ->and($snapshot['affiliates_count'])->toBe(3)
        ->and($snapshot['annual_amount'])->toBe(1200.0)
        ->and($snapshot['payment_frequency'])->toBe('TRIMESTRAL')
        ->and($snapshot['plan_id'])->toBe(7)
        ->and($snapshot['plan_type'])->toBe('Plan Integral')
        ->and($snapshot)->not->toHaveKey('collection_invoice_number')
        ->and($snapshot)->not->toHaveKey('total_to_pay');
});

it('arma el snapshot de una afiliacion corporativa', function (): void {
    $affiliation = new AffiliationCorporate;
    $affiliation->forceFill([
        'id' => 22,
        'code' => 'CORP-200',
        'name_corporate' => 'Acme C.A.',
        'rif' => 'J-123',
        'full_name_contact' => 'Pedro Contacto',
        'status' => 'ACTIVA',
        'code_agency' => 'TDG-202',
        'fee_anual' => 5000,
        'total_amount' => 1250,
        'payment_frequency' => 'MENSUAL',
        'affiliation_type' => 'ESTANDARD',
        'poblation' => 8,
    ]);
    $affiliation->setRelation('corporateAffiliates', new EloquentCollection([
        (object) ['id' => 1],
        (object) ['id' => 2],
    ]));

    $snapshot = CreditReconciliationAffiliationSnapshot::fromCorporate($affiliation);

    expect($snapshot['affiliation_kind'])->toBe('corporate')
        ->and($snapshot['affiliation_id'])->toBeNull()
        ->and($snapshot['affiliation_corporate_id'])->toBe(22)
        ->and($snapshot['affiliation_code'])->toBe('CORP-200')
        ->and($snapshot['affiliation_information'])->toContain('Empresa: Acme C.A. (J-123)')
        ->and($snapshot['affiliates_count'])->toBe(2)
        ->and($snapshot['annual_amount'])->toBe(5000.0)
        ->and($snapshot['payment_frequency'])->toBe('MENSUAL')
        ->and($snapshot['plan_type'])->toBe('ESTANDARD');
});

it('no resuelve empresa aliada sin codigo de agencia', function (): void {
    expect(CreditReconciliationAffiliationSnapshot::whiteCompanyForAgencyCode(null))->toBeNull()
        ->and(CreditReconciliationAffiliationSnapshot::whiteCompanyForAgencyCode(''))->toBeNull();
});

it('no registra movimiento si la afiliacion no esta cargada', function (): void {
    $membership = new PaidMembership;
    $membership->forceFill(['id' => 1, 'total_amount' => 100]);
    $membership->setRelation('affiliation', null);

    expect(WhiteCompanyCreditMovementRecorder::recordIndividualInstallment($membership))->toBeNull();
});
