<?php

declare(strict_types=1);

namespace App\Support\ApiPartners;

use App\Support\TuDrQuote\QuoteFeeMatrix;

/**
 * Tarifas vigentes de los planes Inicial, Ideal y Especial para quien cotiza fuera del portal
 * (el agente de ventas por WhatsApp). La fuente es la misma matriz que usa la propuesta
 * económica del portal ({@see QuoteFeeMatrix}), así el bot y los analistas cotizan siempre
 * con los mismos precios. La matriz se invalida sola al editar una tarifa (FeeObserver).
 *
 * `tarifas` conserva el formato que consume el servicio de PDF del bot; `planes` resume las
 * opciones de cada plan para que el agente no las deduzca. `version` cambia solo cuando cambia
 * un precio, un rango o una cobertura: el cliente la usa para saber si su copia sigue vigente.
 */
final class QuoteTariffFeed
{
    public const CURRENCY = 'USD';

    /**
     * @param  list<array{plan: string, cobertura: int, edad_min: int, edad_max: int, tarifa_anual: float}>|null  $matrix
     * @return array{
     *     moneda: string,
     *     planes: list<array{plan: string, coberturas: list<int>, edad_min: int, edad_max: int}>,
     *     tarifas: list<array{plan: string, cobertura: int, edad_min: int, edad_max: int, tarifa_anual: float}>
     * }
     */
    public static function payload(?array $matrix = null): array
    {
        $matrix ??= QuoteFeeMatrix::all();

        return [
            'moneda' => self::CURRENCY,
            'planes' => self::plans($matrix),
            'tarifas' => array_values($matrix),
        ];
    }

    /**
     * @param  list<array{plan: string, cobertura: int, edad_min: int, edad_max: int, tarifa_anual: float}>  $matrix
     */
    public static function version(array $matrix): string
    {
        return substr(hash('sha256', (string) json_encode(array_values($matrix))), 0, 16);
    }

    /**
     * @param  list<array{plan: string, cobertura: int, edad_min: int, edad_max: int, tarifa_anual: float}>  $matrix
     * @return list<array{plan: string, coberturas: list<int>, edad_min: int, edad_max: int}>
     */
    private static function plans(array $matrix): array
    {
        /** @var array<string, array{plan: string, coberturas: array<int, int>, edad_min: int, edad_max: int}> $plans */
        $plans = [];

        foreach ($matrix as $row) {
            $slug = $row['plan'];

            $plans[$slug] ??= ['plan' => $slug, 'coberturas' => [], 'edad_min' => $row['edad_min'], 'edad_max' => $row['edad_max']];
            $plans[$slug]['edad_min'] = min($plans[$slug]['edad_min'], $row['edad_min']);
            $plans[$slug]['edad_max'] = max($plans[$slug]['edad_max'], $row['edad_max']);

            if ($row['cobertura'] > 0) {
                $plans[$slug]['coberturas'][$row['cobertura']] = $row['cobertura'];
            }
        }

        $result = [];

        foreach ($plans as $plan) {
            $coverages = array_values($plan['coberturas']);
            sort($coverages);

            $result[] = [
                'plan' => $plan['plan'],
                'coberturas' => $coverages,
                'edad_min' => $plan['edad_min'],
                'edad_max' => $plan['edad_max'],
            ];
        }

        return $result;
    }
}
