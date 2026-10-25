<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Filament\Operations\Widgets\Dashboard\Concerns\InteractsWithOperationsDashboard;
use App\Support\Metrics\Corporate\CorporateGeography;
use App\Support\Metrics\Corporate\CorporateMapMetrics;
use Filament\Widgets\Widget;

/**
 * Mapa de Venezuela del panel corporativo: una capa por métrica (ingresos,
 * afiliaciones activas, red comercial, servicios médicos) que se cambia en el
 * navegador sin recargar. Al elegir un estado se ve su ficha con todas las
 * capas y su peso en el total.
 */
class CorporateVenezuelaMap extends Widget
{
    use InteractsWithOperationsDashboard;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.metrics.corporate.map';

    protected int|string|array $columnSpan = 'full';

    /**
     * Escalones de la rampa secuencial (tokens --ops-heat-1…7).
     */
    public const STEPS = 7;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $period = $this->period();

        return [
            'paths' => CorporateGeography::paths(),
            'layers' => CorporateMapMetrics::layers($period),
            'compare' => $period->compare,
            'periodLabel' => $period->label(),
            'previousLabel' => $period->previousLabel(),
            'periodKey' => $period->cacheKey(),
            'steps' => self::STEPS,
        ];
    }
}
