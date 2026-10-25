<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Filament\Operations\Widgets\Dashboard\OperationsKpiTiles;

/**
 * Indicadores de un área del panel corporativo: mismas tarjetas que Operaciones
 * (variación contra el período anterior, tendencia en miniatura y conteo
 * animado), con una línea superior de cifras «a hoy».
 */
abstract class CorporateKpiTiles extends OperationsKpiTiles
{
    /**
     * @return list<array<string, mixed>>
     */
    abstract protected function tiles(): array;

    /**
     * Cifras de contexto a hoy (no dependen del período).
     *
     * @return list<array{label: string, value: int|float, icon: string, prefix?: string}>
     */
    protected function context(): array
    {
        return [];
    }

    protected function contextLabel(): string
    {
        return 'Hoy';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $period = $this->period();

        return [
            'tiles' => $this->tiles(),
            'team' => $this->context(),
            'teamLabel' => $this->contextLabel(),
            'tilesGrid' => 'md:grid-cols-3 2xl:grid-cols-6',
            'compare' => $period->compare,
            'previousLabel' => $period->previousLabel(),
            'periodKey' => $period->cacheKey(),
        ];
    }

    /**
     * Tarjeta de dinero (USD sin decimales).
     *
     * @param  list<float>  $sparkline
     * @return array<string, mixed>
     */
    protected static function moneyTile(string $key, string $label, float $value, float $previous, string $icon, string $hint, array $sparkline = [], bool $lowerIsBetter = false, ?string $note = null): array
    {
        return self::tile($key, $label, $value, $previous, $icon, $hint, $sparkline, lowerIsBetter: $lowerIsBetter, decimals: 0, prefix: 'US$ ', note: $note);
    }

    /**
     * Tarjeta de una cifra a hoy: sin variación contra el período anterior.
     *
     * @return array<string, mixed>
     */
    protected static function snapshotTile(string $key, string $label, int|float $value, string $icon, string $hint, string $prefix = ''): array
    {
        return self::tile($key, $label, $value, $value, $icon, $hint, [], prefix: $prefix, snapshot: true);
    }
}
