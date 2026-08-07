<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets;

/**
 * Variante del gráfico por estado para la zona de stats (header),
 * a ancho completo bajo el resumen MoM.
 */
class AfiliacionesStatusByStatePieChart extends AfiliacionesByStatePieChart
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Total de afiliaciones por estado';

    protected ?string $description = 'Stock activo · individuales + corporativas · distribución geográfica.';
}
