<?php

declare(strict_types=1);

use App\Models\Affiliation;
use App\Models\AnnualCollection;
use App\Models\Collection as CollectionModel;
use App\Models\Commission;
use App\Models\CommissionPayroll;
use App\Models\CreditReconciliation;
use App\Models\PaidMembership;
use App\Models\Sale;
use App\Support\Sales\SaleDeletion;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Estos tests escriben, así que corren dentro de una transacción que siempre se
 * revierte, igual que PlanStructurePersistenceTest. No se deja nada en la base.
 */
beforeEach(function (): void {
    DB::beginTransaction();
});

afterEach(function (): void {
    DB::rollBack();
});

function saleDeletionTestAffiliation(): Affiliation
{
    return Affiliation::query()->forceCreate([
        'code' => 'TEST-SDEL-'.uniqid(),
        'code_agency' => 'TDG-100',
        'status' => 'ACTIVA',
        'effective_date' => '15/09/2027',
    ]);
}

/**
 * @return array{sale: Sale, receipt: PaidMembership, collection: CollectionModel, annual: AnnualCollection, commission: Commission, credit: CreditReconciliation}
 */
function saleDeletionTestSaleWithTrace(Affiliation $affiliation, string $invoice, array $saleOverrides = []): array
{
    $sale = Sale::query()->forceCreate([
        'date_activation' => '15/09/2026',
        'owner_code' => 'TDG-100',
        'code_agency' => 'TDG-100',
        'invoice_number' => $invoice,
        'affiliation_code' => $affiliation->code,
        'persons' => 1,
        'type' => 'AFILIACION INDIVIDUAL',
        'status_payment_commission' => 'POR PAGAR',
        'total_amount' => 100,
        ...$saleOverrides,
    ]);

    $receipt = PaidMembership::query()->forceCreate([
        'affiliation_id' => $affiliation->id,
        'invoice_number' => $invoice,
        'total_amount' => 100,
    ]);

    $collectionInvoice = 'TEST-SDEL-C-'.uniqid();

    $collection = CollectionModel::query()->forceCreate([
        'sale_id' => $sale->id,
        'include_date' => '15/09/2026',
        'owner_code' => 'TDG-100',
        'code_agency' => 'TDG-100',
        'collection_invoice_number' => $collectionInvoice,
        'quote_number' => 'N/A',
        'affiliation_code' => $affiliation->code,
        'persons' => 1,
        'type' => 'AFILIACION INDIVIDUAL',
    ]);

    $annual = AnnualCollection::query()->forceCreate([
        'sale_id' => $sale->id,
        'include_date' => '15/09/2026',
        'owner_code' => 'TDG-100',
        'code_agency' => 'TDG-100',
        'collection_invoice_number' => $collectionInvoice,
        'quote_number' => 'N/A',
        'affiliation_code' => $affiliation->code,
        'persons' => 1,
        'type' => 'AFILIACION INDIVIDUAL',
    ]);

    $commission = Commission::query()->forceCreate([
        'code' => $invoice,
        'sale_id' => $sale->id,
        'affiliate_full_name' => 'PRUEBA',
        'amount' => 100,
        'payment_frequency' => 'ANUAL',
        'created_by' => 'test',
    ]);

    $credit = CreditReconciliation::query()->forceCreate([
        'paid_membership_id' => $receipt->id,
        'affiliation_code' => $affiliation->code,
        'total_to_pay' => 100,
    ]);

    return compact('sale', 'receipt', 'collection', 'annual', 'commission', 'credit');
}

it('borra la venta y todo su rastro sin tocar la afiliación', function (): void {
    $affiliation = saleDeletionTestAffiliation();
    $trace = saleDeletionTestSaleWithTrace($affiliation, 'TEST-SDEL-'.uniqid());

    $report = SaleDeletion::delete([$trace['sale']]);

    expect($report)->toMatchArray([
        'sales' => 1,
        'paid_receipts' => 1,
        'commissions' => 1,
        'collections' => 1,
        'annual_collections' => 1,
        'credit_reconciliations' => 1,
    ])
        ->and(Sale::query()->whereKey($trace['sale']->id)->exists())->toBeFalse()
        ->and(PaidMembership::query()->whereKey($trace['receipt']->id)->exists())->toBeFalse()
        ->and(CollectionModel::query()->whereKey($trace['collection']->id)->exists())->toBeFalse()
        ->and(AnnualCollection::query()->whereKey($trace['annual']->id)->exists())->toBeFalse()
        ->and(Commission::query()->whereKey($trace['commission']->id)->exists())->toBeFalse()
        ->and(CreditReconciliation::query()->whereKey($trace['credit']->id)->exists())->toBeFalse();

    $affiliation->refresh();

    expect($affiliation->exists)->toBeTrue()
        ->and($affiliation->status)->toBe('ACTIVA')
        ->and($affiliation->effective_date)->toBe('15/09/2027');
});

it('no borra el recibo de otra afiliación que comparte el número de factura', function (): void {
    $invoice = 'TEST-SDEL-'.uniqid();
    $trace = saleDeletionTestSaleWithTrace(saleDeletionTestAffiliation(), $invoice);

    $otherReceipt = PaidMembership::query()->forceCreate([
        'affiliation_id' => saleDeletionTestAffiliation()->id,
        'invoice_number' => $invoice,
        'total_amount' => 50,
    ]);

    $report = SaleDeletion::delete([$trace['sale']]);

    expect($report['paid_receipts'])->toBe(1)
        ->and(PaidMembership::query()->whereKey($trace['receipt']->id)->exists())->toBeFalse()
        ->and(PaidMembership::query()->whereKey($otherReceipt->id)->exists())->toBeTrue();
});

it('sin afiliación resoluble solo borra el recibo si la factura no es ambigua', function (): void {
    $invoice = 'TEST-SDEL-'.uniqid();

    $sale = Sale::query()->forceCreate([
        'date_activation' => '15/09/2026',
        'owner_code' => 'TDG-100',
        'code_agency' => 'TDG-100',
        'invoice_number' => $invoice,
        'affiliation_code' => 'TEST-SDEL-INEXISTENTE',
        'persons' => 1,
        'type' => 'AFILIACION INDIVIDUAL',
    ]);

    PaidMembership::query()->forceCreate(['affiliation_id' => 999999999, 'invoice_number' => $invoice]);

    expect(SaleDeletion::paidReceiptsFor($sale))->toHaveCount(1);

    PaidMembership::query()->forceCreate(['affiliation_id' => 999999998, 'invoice_number' => $invoice]);

    expect(SaleDeletion::paidReceiptsFor($sale))->toHaveCount(0);
});

it('bloquea la venta con comisión pagada y no borra nada', function (): void {
    $trace = saleDeletionTestSaleWithTrace(
        saleDeletionTestAffiliation(),
        'TEST-SDEL-'.uniqid(),
        ['status_payment_commission' => SaleDeletion::PAID_COMMISSION_STATUS],
    );

    expect(SaleDeletion::blockingReason($trace['sale']))->toContain('ya fue pagada')
        ->and(fn () => SaleDeletion::delete([$trace['sale']]))->toThrow(InvalidArgumentException::class)
        ->and(Sale::query()->whereKey($trace['sale']->id)->exists())->toBeTrue()
        ->and(Commission::query()->whereKey($trace['commission']->id)->exists())->toBeTrue()
        ->and(PaidMembership::query()->whereKey($trace['receipt']->id)->exists())->toBeTrue();
});

it('bloquea la venta cuya comisión ya está totalizada en una nómina', function (): void {
    $invoice = 'TEST-SDEL-'.uniqid();
    $trace = saleDeletionTestSaleWithTrace(saleDeletionTestAffiliation(), $invoice);

    CommissionPayroll::query()->forceCreate([
        'code' => 'TEST-SDEL-RC',
        'code_pcc' => $invoice,
        'owner_code' => 'TDG-100',
        'code_agency' => 'TDG-100',
        'owner_name' => 'PRUEBA',
        'created_by' => 'test',
    ]);

    expect(SaleDeletion::blockingReason($trace['sale']))->toContain('TEST-SDEL-RC');
});

it('si una venta del lote está bloqueada no borra ninguna', function (): void {
    $free = saleDeletionTestSaleWithTrace(saleDeletionTestAffiliation(), 'TEST-SDEL-'.uniqid());
    $paid = saleDeletionTestSaleWithTrace(
        saleDeletionTestAffiliation(),
        'TEST-SDEL-'.uniqid(),
        ['status_payment_commission' => SaleDeletion::PAID_COMMISSION_STATUS],
    );

    expect(fn () => SaleDeletion::delete([$free['sale'], $paid['sale']]))->toThrow(InvalidArgumentException::class)
        ->and(Sale::query()->whereKey($free['sale']->id)->exists())->toBeTrue()
        ->and(PaidMembership::query()->whereKey($free['receipt']->id)->exists())->toBeTrue()
        ->and(CollectionModel::query()->whereKey($free['collection']->id)->exists())->toBeTrue();
});

it('borra el PDF del recibo solo si ninguna otra venta usa esa factura', function (): void {
    $invoice = 'TEST-SDEL-'.uniqid();
    $first = saleDeletionTestSaleWithTrace(saleDeletionTestAffiliation(), $invoice);
    $second = saleDeletionTestSaleWithTrace(saleDeletionTestAffiliation(), $invoice);

    $path = SaleDeletion::receiptPdfPath($invoice);
    @mkdir(dirname($path), 0755, true);
    file_put_contents($path, '%PDF-test');

    try {
        SaleDeletion::delete([$first['sale']]);
        expect(is_file($path))->toBeTrue();

        SaleDeletion::delete([$second['sale']]);
        expect(is_file($path))->toBeFalse();
    } finally {
        @unlink($path);
    }
});

it('reconoce el tipo de venta con y sin tilde', function (string $type, string $kind): void {
    expect(SaleDeletion::saleKind(new Sale(['type' => $type])))->toBe($kind);
})->with([
    'corporativa con tilde' => ['AFILIACIÓN CORPORATIVA', 'corporate'],
    'corporativa sin tilde' => ['AFILIACION CORPORATIVA', 'corporate'],
    'nuevos negocios' => ['NUEVOS NEGOCIOS', 'company'],
    'individual' => ['AFILIACION INDIVIDUAL', 'individual'],
]);

it('la tabla y la página de edición de ventas borran por el servicio', function (): void {
    $root = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales';
    $table = file_get_contents($root.'/Tables/SalesTable.php');
    $edit = file_get_contents($root.'/Pages/EditSale.php');

    expect($table)
        ->toContain('SaleDeletion::blockedSales($records)')
        ->toContain('SaleDeletion::delete($records)')
        ->toContain('AUDIT_ADMIN_SALES_BULK_DELETE_BLOCKED')
        ->not->toContain('$record->paidMembershipIndividual()->delete()')
        ->and($edit)
        ->toContain('SaleDeletion::blockingReason($record)')
        ->toContain('SaleDeletion::delete([$record])')
        ->toContain('$action->cancel()');
});
