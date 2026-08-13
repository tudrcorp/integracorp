<?php

declare(strict_types=1);

use App\Models\CreditReconciliation;
use App\Models\PaidMembership;
use App\Models\PaidMembershipCorporate;
use App\Support\PaidMembershipDocumentUrl;

uses(Tests\TestCase::class);

it('usa la url absoluta de viveplus sin anteponer el dominio propio', function (): void {
    $documento = 'https://vivepluss.com/storage/notas-credito/NC-TDEC-IND-000391-20260813033523.pdf';

    expect(PaidMembershipDocumentUrl::from($documento))->toBe($documento);
});

it('antepone storage al comprobante con ruta relativa', function (): void {
    $documento = 'vouchers/pago-local.pdf';

    expect(PaidMembershipDocumentUrl::from($documento))->toBe(asset('storage/'.$documento));
});

it('resuelve el comprobante de conciliación desde paid_memberships', function (): void {
    $membership = (new PaidMembership)->forceFill([
        'document_ves' => 'https://vivepluss.com/storage/notas-credito/NC-TDEC-IND-000391-20260813033523.pdf',
        'document_usd' => 'N/A',
    ]);
    $reconciliation = new CreditReconciliation;
    $reconciliation->setRelation('paidMembership', $membership);
    $reconciliation->setRelation('paidMembershipCorporate', null);

    expect(PaidMembershipDocumentUrl::fromReconciliation($reconciliation))
        ->toBe('https://vivepluss.com/storage/notas-credito/NC-TDEC-IND-000391-20260813033523.pdf');
});

it('resuelve el comprobante relativo de conciliación corporativa igual que antes', function (): void {
    $membership = (new PaidMembershipCorporate)->forceFill([
        'document_ves' => 'N/A',
        'document_usd' => 'vouchers/pago-local.pdf',
    ]);
    $reconciliation = new CreditReconciliation;
    $reconciliation->setRelation('paidMembership', null);
    $reconciliation->setRelation('paidMembershipCorporate', $membership);

    expect(PaidMembershipDocumentUrl::fromReconciliation($reconciliation))
        ->toBe(asset('storage/vouchers/pago-local.pdf'));
});

it('arma el comprobante de pagos registrados sin concatenar storage a urls absolutas', function (): void {
    $relationManager = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/RelationManagers/PaidMembershipsRelationManager.php');

    expect($relationManager)
        ->toContain('PaidMembershipDocumentUrl::from($record->document_ves)')
        ->toContain('PaidMembershipDocumentUrl::from($record->document_usd)')
        ->not->toContain("asset('storage/' . \$record->document_ves)")
        ->not->toContain("asset('storage/' . \$record->document_usd)");
});

it('expone en conciliacion de credito la accion para abrir la nota de credito', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CreditReconciliations/Tables/CreditReconciliationsTable.php');

    expect($table)
        ->toContain("Action::make('view_credit_note')")
        ->toContain("label('Ver y descargar')")
        ->toContain('PaidMembershipDocumentUrl::fromReconciliation')
        ->toContain('openUrlInNewTab()');
});
