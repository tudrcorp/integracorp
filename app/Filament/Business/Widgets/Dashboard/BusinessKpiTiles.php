<?php

declare(strict_types=1);

namespace App\Filament\Business\Widgets\Dashboard;

use App\Filament\Metrics\Widgets\Corporate\CorporateKpiTiles;
use App\Support\Business\Dashboard\BusinessDashboardMetrics;

/**
 * Las seis cifras con las que Negocios lee el período: lo que entró, lo que se
 * vendió, lo que se perdió y cómo va el pipeline.
 */
class BusinessKpiTiles extends CorporateKpiTiles
{
    protected function tiles(): array
    {
        $data = BusinessDashboardMetrics::kpis($this->period());
        $finance = $data['finance'];
        $portfolio = $data['portfolio'];
        $commercial = $data['commercial'];

        return [
            self::moneyTile('income', 'Ingresos', (float) $finance['current']['income'], (float) $finance['previous']['income'], 'heroicon-o-banknotes', 'Pagos registrados en el período, en USD.', $finance['sparklines']['income']),
            self::tile('new', 'Afiliaciones nuevas', (int) $portfolio['current']['new'], (int) $portfolio['previous']['new'], 'heroicon-o-user-plus', 'Afiliaciones individuales y corporativas emitidas en el período.', $portfolio['sparklines']['new']),
            self::tile('dropouts', 'Bajas', (int) $portfolio['current']['dropouts'], (int) $portfolio['previous']['dropouts'], 'heroicon-o-user-minus', 'Afiliaciones excluidas, inactivadas o anuladas en el período. Aquí, bajar es mejor.', $portfolio['sparklines']['dropouts'], lowerIsBetter: true),
            self::tile('quotes', 'Cotizaciones', (int) $commercial['current']['quotes'], (int) $commercial['previous']['quotes'], 'heroicon-o-document-text', 'Cotizaciones individuales y corporativas emitidas en el período.', $commercial['sparklines']['quotes']),
            self::tile('new_agents', 'Agentes nuevos', (int) $commercial['current']['new_agents'], (int) $commercial['previous']['new_agents'], 'heroicon-o-users', 'Agentes registrados en el período.', $commercial['sparklines']['agents']),
        ];
    }

    protected function context(): array
    {
        $data = BusinessDashboardMetrics::kpis($this->period());

        return [
            ['label' => 'afiliaciones activas', 'value' => (int) $data['portfolio']['snapshot']['active'], 'icon' => 'heroicon-o-document-check'],
            ['label' => 'personas afiliadas', 'value' => (int) $data['portfolio']['snapshot']['lives'], 'icon' => 'heroicon-o-heart'],
            ['label' => 'agentes activos', 'value' => (int) $data['commercial']['network']['agents'], 'icon' => 'heroicon-o-users'],
            ['label' => 'agencias activas', 'value' => (int) $data['commercial']['network']['agencies'], 'icon' => 'heroicon-o-building-office-2'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [...parent::getViewData(), 'tilesGrid' => 'md:grid-cols-3 2xl:grid-cols-5'];
    }

    protected function contextLabel(): string
    {
        return 'Negocio hoy';
    }
}
