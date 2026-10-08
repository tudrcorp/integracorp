<?php

declare(strict_types=1);

namespace App\Support\Sales;

/**
 * Montos en bolívares de las líneas de una factura cuyo total ya está en Bs.
 *
 * Las líneas nacen en dólares (tarifa del plan por período). Se convierten con
 * la tasa implícita del cobro —total en Bs ÷ suma en US$—, la única que
 * reproduce exactamente lo pagado aunque la venta se haya cobrado en bolívares
 * sin tasa registrada. El ajuste de céntimos va a la línea de mayor monto, para
 * que la suma de las líneas sea siempre igual al total.
 */
final class InvoiceVesLineAmounts
{
    /**
     * @param  list<float|int|string|null>  $usdAmounts
     * @return array{rate: float|null, lines: list<float|null>}
     */
    public static function distribute(array $usdAmounts, float|int|string|null $totalVes): array
    {
        $usdCents = array_map(
            static fn (mixed $amount): int => is_numeric($amount) ? max(0, (int) round((float) $amount * 100)) : 0,
            array_values($usdAmounts),
        );
        $totalVesCents = is_numeric($totalVes) ? (int) round((float) $totalVes * 100) : 0;
        $usdTotalCents = array_sum($usdCents);

        if ($usdCents === [] || $usdTotalCents <= 0 || $totalVesCents <= 0) {
            return ['rate' => null, 'lines' => array_fill(0, count($usdCents), null)];
        }

        $linesCents = array_map(
            static fn (int $cents): int => intdiv($cents * $totalVesCents * 2 + $usdTotalCents, $usdTotalCents * 2),
            $usdCents,
        );

        $largest = array_keys($usdCents, max($usdCents), true)[0];
        $linesCents[$largest] += $totalVesCents - array_sum($linesCents);

        return [
            'rate' => $totalVesCents / $usdTotalCents,
            'lines' => array_map(static fn (int $cents): float => $cents / 100, $linesCents),
        ];
    }

    public static function format(float|int|string|null $amount): string
    {
        if (! is_numeric($amount)) {
            return '—';
        }

        return number_format((float) $amount, 2, ',', '.').' Bs.';
    }
}
