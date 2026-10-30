<?php

declare(strict_types=1);

namespace App\Filament\Business\Widgets\Dashboard;

use App\Filament\Metrics\Widgets\Corporate\CorporateVenezuelaMap;
use App\Support\Business\Dashboard\BusinessDashboardMetrics;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod as Period;

/**
 * Mapa de Venezuela de Negocios: dónde entra el dinero, dónde se afilia y dónde
 * está la red. Sin la capa de servicios médicos, que es de Operaciones.
 */
class BusinessVenezuelaMap extends CorporateVenezuelaMap
{
    protected function layers(Period $period): array
    {
        return BusinessDashboardMetrics::mapLayers($period);
    }
}
