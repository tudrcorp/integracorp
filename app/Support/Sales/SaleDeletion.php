<?php

declare(strict_types=1);

namespace App\Support\Sales;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\AnnualCollection;
use App\Models\Collection as CollectionModel;
use App\Models\Commission;
use App\Models\CommissionPayroll;
use App\Models\CompanyPaidMembership;
use App\Models\CreditReconciliation;
use App\Models\PaidMembership;
use App\Models\PaidMembershipCorporate;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Elimina una venta y todo el rastro que nació de ella (recibo de pago, comisión,
 * cuotas de cobranza, cobranza anual, movimientos de crédito de empresa aliada y
 * PDFs generados) sin tocar la afiliación, sus afiliados ni sus fechas.
 *
 * Los números de recibo no son únicos en los datos reales (hay recibos de varias
 * afiliaciones con la misma factura), así que el recibo se identifica por factura
 * **y** dueño (afiliación, afiliación corporativa o empresa), nunca solo por factura.
 */
final class SaleDeletion
{
    public const PAID_COMMISSION_STATUS = 'COMISION PAGADA';

    /**
     * @param  iterable<Sale>  $sales
     * @return array<int, string> motivo de bloqueo indexado por id de venta
     */
    public static function blockedSales(iterable $sales): array
    {
        $blocked = [];

        foreach ($sales as $sale) {
            $reason = self::blockingReason($sale);

            if ($reason !== null) {
                $blocked[(int) $sale->getKey()] = $reason;
            }
        }

        return $blocked;
    }

    public static function blockingReason(Sale $sale): ?string
    {
        $invoice = (string) ($sale->invoice_number ?: 'sin número');

        if ($sale->status_payment_commission === self::PAID_COMMISSION_STATUS) {
            return "La comisión de la venta {$invoice} ya fue pagada.";
        }

        $commissionCodes = Commission::query()
            ->where('sale_id', $sale->getKey())
            ->whereNotNull('code')
            ->pluck('code');

        if ($commissionCodes->isEmpty()) {
            return null;
        }

        $payrollCode = CommissionPayroll::query()
            ->whereIn('code_pcc', $commissionCodes)
            ->value('code');

        if ($payrollCode !== null) {
            return "La comisión de la venta {$invoice} ya está totalizada en la nómina {$payrollCode}.";
        }

        return null;
    }

    /**
     * Elimina las ventas en una sola transacción: o se borran todas con su rastro o
     * no se borra ninguna. Los PDFs se eliminan después de confirmar la transacción.
     *
     * @param  iterable<Sale>  $sales
     * @return array{sales: int, paid_receipts: int, commissions: int, collections: int, annual_collections: int, credit_reconciliations: int, files: int}
     *
     * @throws InvalidArgumentException si alguna venta tiene la comisión liquidada
     */
    public static function delete(iterable $sales): array
    {
        $saleIds = [];

        foreach ($sales as $sale) {
            $saleIds[] = (int) $sale->getKey();
        }

        $report = [
            'sales' => 0,
            'paid_receipts' => 0,
            'commissions' => 0,
            'collections' => 0,
            'annual_collections' => 0,
            'credit_reconciliations' => 0,
            'files' => 0,
        ];

        if ($saleIds === []) {
            return $report;
        }

        /** @var array{receipts: list<string>, notices: list<string>} $candidateFiles */
        $candidateFiles = DB::transaction(function () use ($saleIds, &$report): array {
            $lockedSales = Sale::query()
                ->whereKey($saleIds)
                ->lockForUpdate()
                ->get();

            $blocked = self::blockedSales($lockedSales);

            if ($blocked !== []) {
                throw new InvalidArgumentException(implode(' ', $blocked));
            }

            $files = ['receipts' => [], 'notices' => []];

            foreach ($lockedSales as $sale) {
                $receipts = self::paidReceiptsFor($sale);
                $collections = CollectionModel::query()
                    ->where('sale_id', $sale->getKey())
                    ->get(['id', 'collection_invoice_number']);

                $report['credit_reconciliations'] += self::deleteCreditReconciliations($receipts, $collections);
                $report['collections'] += CollectionModel::query()->whereKey($collections->modelKeys())->delete();
                $report['annual_collections'] += AnnualCollection::query()->where('sale_id', $sale->getKey())->delete();
                $report['commissions'] += Commission::query()->where('sale_id', $sale->getKey())->delete();

                if ($receipts->isNotEmpty()) {
                    $report['paid_receipts'] += $receipts->first()::query()->whereKey($receipts->modelKeys())->delete();
                }

                $report['sales'] += Sale::query()->whereKey($sale->getKey())->delete();

                if (filled($sale->invoice_number)) {
                    $files['receipts'][] = (string) $sale->invoice_number;
                }

                foreach ($collections as $collection) {
                    if (filled($collection->collection_invoice_number)) {
                        $files['notices'][] = (string) $collection->collection_invoice_number;
                    }
                }
            }

            return $files;
        });

        $report['files'] = self::deleteOrphanedPdfs($candidateFiles['receipts'], $candidateFiles['notices']);

        return $report;
    }

    public static function receiptPdfPath(string $invoiceNumber): string
    {
        return public_path('storage/reciboDePago/RDP-'.$invoiceNumber.'.pdf');
    }

    public static function collectionNoticePdfPath(string $collectionInvoiceNumber): string
    {
        return public_path('storage/avisoDeCobro/ADP-'.$collectionInvoiceNumber.'.pdf');
    }

    /**
     * @return EloquentCollection<int, PaidMembership|PaidMembershipCorporate|CompanyPaidMembership>
     */
    public static function paidReceiptsFor(Sale $sale): EloquentCollection
    {
        $invoice = $sale->invoice_number;

        if (blank($invoice)) {
            return new EloquentCollection;
        }

        [$query, $ownerColumn, $ownerId] = match (self::saleKind($sale)) {
            'corporate' => [
                PaidMembershipCorporate::query(),
                'affiliation_corporate_id',
                AffiliationCorporate::query()->where('code', $sale->affiliation_code)->value('id'),
            ],
            'company' => [CompanyPaidMembership::query(), 'company_id', $sale->company_id],
            default => [
                PaidMembership::query(),
                'affiliation_id',
                Affiliation::query()->where('code', $sale->affiliation_code)->value('id'),
            ],
        };

        /** @var Builder $query */
        $query->where('invoice_number', $invoice);

        if ($ownerId !== null) {
            return $query->where($ownerColumn, $ownerId)->get();
        }

        /**
         * Sin dueño resoluble (p. ej. la afiliación ya no existe) solo se acepta el
         * recibo si la factura lo identifica sin ambigüedad.
         */
        $candidates = $query->limit(2)->get();
        $invoiceSharedWithOtherSale = Sale::query()
            ->where('invoice_number', $invoice)
            ->whereKeyNot($sale->getKey())
            ->exists();

        if ($candidates->count() === 1 && ! $invoiceSharedWithOtherSale) {
            return $candidates;
        }

        return new EloquentCollection;
    }

    /**
     * @return 'individual'|'corporate'|'company'
     */
    public static function saleKind(Sale $sale): string
    {
        return match (Str::upper(Str::ascii(trim((string) $sale->type)))) {
            'AFILIACION CORPORATIVA' => 'corporate',
            'NUEVOS NEGOCIOS' => 'company',
            default => 'individual',
        };
    }

    /**
     * @param  EloquentCollection<int, PaidMembership|PaidMembershipCorporate|CompanyPaidMembership>  $receipts
     * @param  EloquentCollection<int, CollectionModel>  $collections
     */
    private static function deleteCreditReconciliations(EloquentCollection $receipts, EloquentCollection $collections): int
    {
        $receiptIds = $receipts->modelKeys();
        $collectionIds = $collections->modelKeys();
        $receiptClass = $receipts->isNotEmpty() ? $receipts->first()::class : null;
        $receiptColumn = match ($receiptClass) {
            PaidMembership::class => 'paid_membership_id',
            PaidMembershipCorporate::class => 'paid_membership_corporate_id',
            default => null,
        };

        if ($collectionIds === [] && ($receiptColumn === null || $receiptIds === [])) {
            return 0;
        }

        return CreditReconciliation::query()
            ->where(function (Builder $query) use ($receiptColumn, $receiptIds, $collectionIds): void {
                if ($receiptColumn !== null && $receiptIds !== []) {
                    $query->orWhereIn($receiptColumn, $receiptIds);
                }

                if ($collectionIds !== []) {
                    $query->orWhereIn('collection_id', $collectionIds);
                }
            })
            ->delete();
    }

    /**
     * Borra los PDFs solo si ya ninguna venta o cuota sigue usando ese número:
     * hay facturas repetidas y el archivo podría pertenecer a otro registro.
     *
     * @param  list<string>  $receiptInvoices
     * @param  list<string>  $noticeInvoices
     */
    private static function deleteOrphanedPdfs(array $receiptInvoices, array $noticeInvoices): int
    {
        $deleted = 0;

        foreach (array_unique($receiptInvoices) as $invoice) {
            if (! Sale::query()->where('invoice_number', $invoice)->exists()) {
                $deleted += self::deleteFileIfExists(self::receiptPdfPath($invoice)) ? 1 : 0;
            }
        }

        foreach (array_unique($noticeInvoices) as $invoice) {
            if (! CollectionModel::query()->where('collection_invoice_number', $invoice)->exists()) {
                $deleted += self::deleteFileIfExists(self::collectionNoticePdfPath($invoice)) ? 1 : 0;
            }
        }

        return $deleted;
    }

    private static function deleteFileIfExists(string $path): bool
    {
        return is_file($path) && @unlink($path);
    }
}
