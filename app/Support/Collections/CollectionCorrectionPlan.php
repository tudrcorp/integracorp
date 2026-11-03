<?php

declare(strict_types=1);

namespace App\Support\Collections;

use App\Models\AffiliationCorporate;
use App\Models\Collection;
use App\Models\CollectionAdjustment;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Corrección puntual de cuotas y de la afiliación corporativa a partir de un plan JSON
 * revisado y aprobado (p. ej. `database/corrections/2026_11_03_casalbeach_poblacion.json`).
 *
 * Cada campo declara el valor que DEBE tener hoy (`expect`) y el nuevo (`set`). Si un solo
 * valor no coincide, no se escribe nada: así el mismo plan puede correrse en producción sin
 * pisar datos que cambiaron. Todo va en una transacción, cada cuota deja su antes/después en
 * `collection_adjustments` (modo `poblacion`, un mismo lote) y la afiliación en SecurityAudit.
 * Con `revert` el plan se aplica al revés (lo nuevo pasa a ser lo esperado).
 */
final class CollectionCorrectionPlan
{
    public const string MODE = 'poblacion';

    public const int MIN_REASON_LENGTH = 15;

    /** Columnas que el plan puede tocar. Cualquier otra se rechaza. */
    public const array COLLECTION_COLUMNS = ['total_amount', 'payment_frequency', 'persons', 'affiliate_ci_rif'];

    public const array AFFILIATION_COLUMNS = ['fee_anual', 'total_amount'];

    /**
     * @param  array<string, mixed>  $plan
     * @return array{status: 'pending'|'applied'|'mismatch', rows: list<array{target: string, column: string, current: string, expected: string, new: string, ok: bool, done: bool}>}
     */
    public static function inspect(array $plan, bool $revert = false): array
    {
        $plan = self::normalize($plan, $revert);
        $rows = [];

        foreach ($plan['collections'] as $item) {
            $collection = Collection::query()->find($item['id']);
            $rows = [...$rows, ...self::compare('Cuota '.$item['invoice'], $collection?->getAttributes() ?? [], $item, $collection !== null && self::belongs($collection, $plan, $item))];
        }

        if ($plan['affiliation'] !== null) {
            $owner = AffiliationCorporate::query()->find($plan['affiliation']['id']);
            $rows = [...$rows, ...self::compare('Afiliación '.$plan['affiliation_code'], $owner?->getAttributes() ?? [], $plan['affiliation'], $owner?->code === $plan['affiliation_code'])];
        }

        return ['status' => self::statusOf($rows), 'rows' => $rows];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array{batch_uuid: string, collections: int, affiliation: bool}
     */
    public static function apply(array $plan, string $actorName, bool $revert = false): array
    {
        $plan = self::normalize($plan, $revert);
        $batch = (string) Str::uuid();
        $reason = ($revert ? 'REVERSO: ' : '').$plan['reason'];

        return DB::transaction(function () use ($plan, $batch, $reason, $actorName, $revert): array {
            $collections = Collection::query()
                ->whereIn('id', array_column($plan['collections'], 'id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $owner = $plan['affiliation'] !== null
                ? AffiliationCorporate::query()->whereKey($plan['affiliation']['id'])->lockForUpdate()->first()
                : null;

            $rows = [];

            foreach ($plan['collections'] as $item) {
                $collection = $collections->get($item['id']);
                $rows = [...$rows, ...self::compare('Cuota '.$item['invoice'], $collection?->getAttributes() ?? [], $item, $collection !== null && self::belongs($collection, $plan, $item))];
            }

            if ($plan['affiliation'] !== null) {
                $rows = [...$rows, ...self::compare('Afiliación '.$plan['affiliation_code'], $owner?->getAttributes() ?? [], $plan['affiliation'], $owner?->code === $plan['affiliation_code'])];
            }

            $status = self::statusOf($rows);

            if ($status !== 'pending') {
                throw new RuntimeException($status === 'applied'
                    ? 'El plan ya está aplicado: no hay nada que cambiar.'
                    : 'Los datos actuales no coinciden con lo esperado; no se aplicó nada. Revise la simulación.');
            }

            foreach ($plan['collections'] as $item) {
                /** @var Collection $collection */
                $collection = $collections->get($item['id']);
                $changes = [];

                foreach ($item['set'] as $column => $value) {
                    $changes[$column] = ['before' => $collection->getAttribute($column), 'after' => $value];
                    $collection->setAttribute($column, $value);
                }

                $collection->save();

                CollectionAdjustment::query()->create([
                    'collection_id' => $collection->getKey(),
                    'affiliation_code' => $collection->affiliation_code,
                    'collection_invoice_number' => $collection->collection_invoice_number,
                    'batch_uuid' => $batch,
                    'mode' => self::MODE,
                    'changes' => $changes,
                    'reason' => $reason,
                    'performed_by_id' => null,
                    'performed_by_name' => $actorName,
                    'ip' => null,
                    'user_agent' => 'artisan collections:apply-correction',
                ]);
            }

            if ($owner instanceof AffiliationCorporate) {
                $changes = [];

                foreach ($plan['affiliation']['set'] as $column => $value) {
                    $changes[$column] = ['before' => $owner->getAttribute($column), 'after' => $value];
                    $owner->setAttribute($column, $value);
                }

                $owner->save();

                SecurityAudit::log('AUDIT_ADMIN_CORPORATE_CORRECTION_PLAN_APPLIED', 'artisan.collections.apply-correction', [
                    'affiliation_corporate_id' => $owner->getKey(),
                    'affiliation_code' => $owner->code,
                    'batch_uuid' => $batch,
                    'revert' => $revert,
                    'reason' => $reason,
                    'actor' => $actorName,
                    'affiliation_changes' => $changes,
                    'collection_ids' => array_column($plan['collections'], 'id'),
                ]);
            }

            return ['batch_uuid' => $batch, 'collections' => count($plan['collections']), 'affiliation' => $owner instanceof AffiliationCorporate];
        });
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array{affiliation_code: string, reason: string, collections: list<array{id: int, invoice: string, expect: array<string, string>, set: array<string, string>}>, affiliation: array{id: int, expect: array<string, string>, set: array<string, string>}|null}
     */
    private static function normalize(array $plan, bool $revert): array
    {
        $code = trim((string) ($plan['affiliation_code'] ?? ''));
        $reason = trim((string) ($plan['reason'] ?? ''));

        if ($code === '') {
            throw new InvalidArgumentException('El plan no indica affiliation_code.');
        }

        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            throw new InvalidArgumentException('El plan necesita un motivo de al menos '.self::MIN_REASON_LENGTH.' caracteres.');
        }

        $collections = [];

        foreach ((array) ($plan['collections'] ?? []) as $item) {
            $collections[] = ['id' => (int) ($item['id'] ?? 0), 'invoice' => (string) ($item['invoice'] ?? '')] + self::pairs($item, self::COLLECTION_COLUMNS, $revert);
        }

        $affiliation = null;

        if (isset($plan['affiliation_corporate'])) {
            $affiliation = ['id' => (int) ($plan['affiliation_corporate']['id'] ?? 0)] + self::pairs($plan['affiliation_corporate'], self::AFFILIATION_COLUMNS, $revert);
        }

        if ($collections === [] && $affiliation === null) {
            throw new InvalidArgumentException('El plan no tiene cambios.');
        }

        return ['affiliation_code' => $code, 'reason' => $reason, 'collections' => $collections, 'affiliation' => $affiliation];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $allowed
     * @return array{expect: array<string, string>, set: array<string, string>}
     */
    private static function pairs(array $item, array $allowed, bool $revert): array
    {
        $expect = array_map('strval', (array) ($item['expect'] ?? []));
        $set = array_map('strval', (array) ($item['set'] ?? []));

        if ($set === [] || array_diff(array_keys($set), $allowed) !== [] || array_keys($expect) !== array_keys($set)) {
            throw new InvalidArgumentException('Cada cambio debe declarar «expect» y «set» con las mismas columnas permitidas ('.implode(', ', $allowed).').');
        }

        return $revert ? ['expect' => $set, 'set' => $expect] : ['expect' => $expect, 'set' => $set];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array{expect: array<string, string>, set: array<string, string>}  $item
     * @return list<array{target: string, column: string, current: string, expected: string, new: string, ok: bool, done: bool}>
     */
    private static function compare(string $target, array $attributes, array $item, bool $found): array
    {
        $rows = [];

        foreach ($item['set'] as $column => $new) {
            $current = $found ? (string) ($attributes[$column] ?? '') : '(no existe o no es de la afiliación)';
            $rows[] = [
                'target' => $target,
                'column' => $column,
                'current' => $current,
                'expected' => $item['expect'][$column],
                'new' => $new,
                'ok' => $found && self::same($current, $item['expect'][$column]),
                'done' => $found && self::same($current, $new),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private static function statusOf(array $rows): string
    {
        if ($rows !== [] && collect($rows)->every(fn (array $row): bool => $row['done'])) {
            return 'applied';
        }

        return collect($rows)->every(fn (array $row): bool => $row['ok']) ? 'pending' : 'mismatch';
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $item
     */
    private static function belongs(Collection $collection, array $plan, array $item): bool
    {
        return trim((string) $collection->affiliation_code) === $plan['affiliation_code']
            && trim((string) $collection->collection_invoice_number) === $item['invoice'];
    }

    private static function same(string $a, string $b): bool
    {
        $a = trim($a);
        $b = trim($b);

        if (is_numeric($a) && is_numeric($b)) {
            return (int) round(((float) $a) * 100) === (int) round(((float) $b) * 100);
        }

        return $a === $b;
    }
}
