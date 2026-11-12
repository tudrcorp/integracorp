<?php

declare(strict_types=1);

namespace App\Support\Collections;

use App\Models\CollectionMovementLine;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Líneas que explican al cliente por qué cambió el monto de un aviso de cobro:
 * prorrateos de inclusiones y exclusiones, créditos aplicados y reversos.
 *
 * La cuota ya recalculada de cada persona no se lista (las filas de plan del aviso
 * ya la reflejan): solo lo que se suma o resta por días o créditos.
 */
final class CollectionMovementBreakdown
{
    /** Tipos que se muestran al cliente. */
    private const VISIBLE = [CollectionMovementLine::KIND_PRORATION, CollectionMovementLine::KIND_CREDIT, CollectionMovementLine::KIND_REVERSAL];

    /**
     * @return list<array{description: string, amount: float}>
     */
    public static function forInvoice(?string $invoice): array
    {
        if (blank($invoice) || ! self::available()) {
            return [];
        }

        try {
            return CollectionMovementLine::query()
                ->whereHas('collection', fn ($query) => $query->where('collection_invoice_number', $invoice))
                ->where('reverted', false)
                ->whereIn('kind', self::VISIBLE)
                ->orderBy('id')
                ->get(['description', 'amount'])
                ->map(fn (CollectionMovementLine $line): array => ['description' => $line->description, 'amount' => (float) $line->amount])
                ->all();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * @param  list<array{amount: float}>  $lines
     */
    public static function total(array $lines): float
    {
        return round(array_sum(array_column($lines, 'amount')), 2);
    }

    private static function available(): bool
    {
        static $available = null;

        return $available ??= Schema::hasTable('collection_movement_lines');
    }
}
