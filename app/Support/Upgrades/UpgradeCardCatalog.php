<?php

declare(strict_types=1);

namespace App\Support\Upgrades;

use App\Models\Upgrade;
use App\Models\UpgradeCoverage;
use App\Models\UpgradeCoverageAgeRange;
use App\Support\PlanGenerators\PlanGeneratorUpgrades;

/**
 * Tarjetas de upgrades del cotizador individual: una por upgrade activo con al menos una cobertura
 * con precios. Se arma una sola vez por petición (la galería y cada fila del repetidor la leen).
 *
 * Texto de la tarjeta (decidido por Negocios, 29/10/2026): arriba «Cobertura US$ 2.000 · 18 a 64
 * años» (o «3 coberturas · 0 a 120 años», con las edades de todas sus coberturas), el nombre en grande y «desde US$ 35/año».
 */
final class UpgradeCardCatalog
{
    /** Fondos de las tarjetas sin imagen, elegidos de forma estable por upgrade. */
    private const FALLBACK_GRADIENTS = [
        ['#052F60', '#2d89ca'],
        ['#0f766e', '#14b8a6'],
        ['#7c2d12', '#ea580c'],
        ['#4c1d95', '#8b5cf6'],
        ['#1e3a8a', '#0ea5e9'],
        ['#831843', '#ec4899'],
    ];

    /**
     * @return array<int, array{id: int, name: string, image_url: ?string, gradient: array{0: string, 1: string}, eyebrow: string, from_label: string, default_coverage_id: int, coverages: array<int, array{id: int, label: string, ages: string, from: float}>}>
     */
    public static function cards(): array
    {
        return once(static function (): array {
            $upgrades = Upgrade::query()
                ->active()
                ->with(['activeCoverages.activeAgeRanges'])
                ->orderBy('name')
                ->get();

            $cards = [];

            foreach ($upgrades as $upgrade) {
                $coverages = [];
                $allAgePrices = [];

                foreach ($upgrade->activeCoverages->sortBy(fn (UpgradeCoverage $coverage): float => (float) ($coverage->amount ?? 0)) as $coverage) {
                    if ($coverage->activeAgeRanges->isEmpty()) {
                        continue;
                    }

                    $agePrices = $coverage->activeAgeRanges
                        ->map(fn (UpgradeCoverageAgeRange $range): array => ['age_from' => (int) $range->age_from, 'age_to' => (int) $range->age_to, 'price' => (float) $range->price])
                        ->values()
                        ->all();

                    $allAgePrices = [...$allAgePrices, ...$agePrices];
                    $coverages[(int) $coverage->getKey()] = [
                        'id' => (int) $coverage->getKey(),
                        'label' => self::coverageLabel($coverage),
                        'ages' => PlanGeneratorUpgrades::coverageSpansLabel($agePrices),
                        'from' => round((float) $coverage->activeAgeRanges->min('price'), 2),
                    ];
                }

                if ($coverages === []) {
                    continue;
                }

                $first = reset($coverages);
                $from = min(array_column($coverages, 'from'));

                $cards[(int) $upgrade->getKey()] = [
                    'id' => (int) $upgrade->getKey(),
                    'name' => (string) $upgrade->name,
                    'image_url' => $upgrade->imageUrl(),
                    'gradient' => self::FALLBACK_GRADIENTS[(int) $upgrade->getKey() % count(self::FALLBACK_GRADIENTS)],
                    'eyebrow' => count($coverages) === 1
                        ? $first['label'].' · '.$first['ages']
                        : count($coverages).' coberturas · '.PlanGeneratorUpgrades::coverageSpansLabel($allAgePrices),
                    'from_label' => 'desde '.self::money($from).'/año',
                    'default_coverage_id' => $first['id'],
                    'coverages' => $coverages,
                ];
            }

            return $cards;
        });
    }

    /**
     * Upgrade al que pertenece una cobertura de las tarjetas.
     */
    public static function upgradeIdForCoverage(mixed $coverageId): ?int
    {
        if (! is_numeric($coverageId)) {
            return null;
        }

        foreach (self::cards() as $card) {
            if (isset($card['coverages'][(int) $coverageId])) {
                return $card['id'];
            }
        }

        return null;
    }

    /**
     * «Cobertura US$ 2.000» o «Cobertura única» si el upgrade no declara monto.
     */
    private static function coverageLabel(UpgradeCoverage $coverage): string
    {
        return $coverage->amount !== null && (float) $coverage->amount > 0
            ? 'Cobertura '.self::money((float) $coverage->amount, decimals: false)
            : 'Cobertura única';
    }

    private static function money(float $amount, bool $decimals = true): string
    {
        return 'US$ '.number_format($amount, $decimals && floor($amount) !== $amount ? 2 : 0, ',', '.');
    }
}
