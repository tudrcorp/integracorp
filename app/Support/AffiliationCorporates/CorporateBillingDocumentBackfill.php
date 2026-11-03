<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates;

use App\Models\Collection;
use App\Models\CollectionAdjustment;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pone el RIF de la empresa en las ventas y cuotas corporativas que guardaron otro documento
 * (la cédula del contacto, «...» o vacío). Ver {@see CorporateBillingDocument}.
 *
 * Se calcula sobre los datos del momento, así que puede correrse en cualquier base y repetirse:
 * lo ya corregido no vuelve a aparecer. Solo toca `affiliate_ci_rif`; nunca la factura emitida
 * (`sales.invoice_snapshot`). Cada cuota deja su antes/después en `collection_adjustments`
 * (modo `documento`) y las ventas en SecurityAudit, todo bajo un mismo lote.
 */
final class CorporateBillingDocumentBackfill
{
    public const string MODE = 'documento';

    public const string REASON = 'RIF de la empresa en lugar de la cédula del contacto u otro documento (corrección del 03/11/2026).';

    /**
     * @return array{collections: list<array{id: int, code: string, invoice: string, before: string, after: string}>, sales: list<array{id: int, code: string, invoice: string, before: string, after: string}>}
     */
    public static function plan(): array
    {
        return ['collections' => self::rows('collections', 'collection_invoice_number'), 'sales' => self::rows('sales', 'invoice_number')];
    }

    /**
     * @return array{batch_uuid: string, collections: int, sales: int}
     */
    public static function apply(string $actorName): array
    {
        $batch = (string) Str::uuid();

        return DB::transaction(function () use ($batch, $actorName): array {
            $plan = self::plan();
            $collections = Collection::query()->whereIn('id', array_column($plan['collections'], 'id'))->lockForUpdate()->get()->keyBy('id');

            foreach ($plan['collections'] as $row) {
                $collection = $collections->get($row['id']);

                if (! $collection instanceof Collection || (string) $collection->affiliate_ci_rif !== $row['before']) {
                    continue;
                }

                $collection->affiliate_ci_rif = $row['after'];
                $collection->save();

                CollectionAdjustment::query()->create([
                    'collection_id' => $collection->getKey(),
                    'affiliation_code' => $collection->affiliation_code,
                    'collection_invoice_number' => $collection->collection_invoice_number,
                    'batch_uuid' => $batch,
                    'mode' => self::MODE,
                    'changes' => ['affiliate_ci_rif' => ['before' => $row['before'], 'after' => $row['after']]],
                    'reason' => self::REASON,
                    'performed_by_id' => null,
                    'performed_by_name' => $actorName,
                    'ip' => null,
                    'user_agent' => 'artisan corporates:backfill-billing-rif',
                ]);
            }

            $sales = DB::table('sales')->whereIn('id', array_column($plan['sales'], 'id'))->lockForUpdate()->get(['id', 'affiliate_ci_rif'])->keyBy('id');

            foreach ($plan['sales'] as $row) {
                $sale = $sales->get($row['id']);

                if ($sale === null || (string) ($sale->affiliate_ci_rif ?? '') !== $row['before']) {
                    continue;
                }

                DB::table('sales')->where('id', $row['id'])->update(['affiliate_ci_rif' => $row['after'], 'updated_at' => now()]);
            }

            SecurityAudit::log('AUDIT_ADMIN_CORPORATE_BILLING_RIF_BACKFILLED', 'artisan.corporates.backfill-billing-rif', [
                'batch_uuid' => $batch,
                'actor' => $actorName,
                'reason' => self::REASON,
                'collections' => $plan['collections'],
                'sales' => $plan['sales'],
            ]);

            return ['batch_uuid' => $batch, 'collections' => count($plan['collections']), 'sales' => count($plan['sales'])];
        });
    }

    /**
     * @return list<array{id: int, code: string, invoice: string, before: string, after: string}>
     */
    private static function rows(string $table, string $invoiceColumn): array
    {
        return DB::table("{$table} as x")
            ->join('affiliation_corporates as ac', 'ac.code', '=', 'x.affiliation_code')
            ->whereNull('ac.deleted_at')
            ->whereRaw("TRIM(IFNULL(ac.rif, '')) <> ''")
            ->whereRaw("TRIM(IFNULL(x.affiliate_ci_rif, '')) <> TRIM(ac.rif)")
            ->orderBy('x.id')
            ->get(['x.id', 'x.affiliation_code', "x.{$invoiceColumn} as invoice", 'x.affiliate_ci_rif', 'ac.rif'])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'code' => (string) $row->affiliation_code,
                'invoice' => (string) $row->invoice,
                'before' => (string) ($row->affiliate_ci_rif ?? ''),
                'after' => trim((string) $row->rif),
            ])
            ->all();
    }
}
