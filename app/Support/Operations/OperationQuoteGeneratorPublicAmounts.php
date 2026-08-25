<?php

declare(strict_types=1);

namespace App\Support\Operations;

final class OperationQuoteGeneratorPublicAmounts
{
    public static function applyProfit(mixed $amount, mixed $porcentajeGanancia): float
    {
        return CoordinationServiceItemsManager::manageQuoteTotal($amount ?? 0, $porcentajeGanancia) ?? 0.0;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public static function itemsWithProfit(array $items, mixed $porcentajeGanancia): array
    {
        return array_values(array_map(
            static function (mixed $item) use ($porcentajeGanancia): array {
                $row = is_array($item) ? $item : [];

                if (array_key_exists('unit_price_usd', $row)) {
                    $row['unit_price_usd'] = self::applyProfit($row['unit_price_usd'], $porcentajeGanancia);
                }

                if (array_key_exists('unit_price_ves', $row)) {
                    $row['unit_price_ves'] = self::applyProfit($row['unit_price_ves'], $porcentajeGanancia);
                }

                return $row;
            },
            $items
        ));
    }
}
