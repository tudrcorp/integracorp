<?php

declare(strict_types=1);

namespace App\Filament\Shared\Renovations\Concerns;

use App\Support\Filament\SummaryCards;
use App\Support\Renovations\UpcomingRenovationCounts;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Tarjetas con la cantidad de renovaciones de los próximos 15, 30 y 45 días en
 * el listado de renovaciones (individuales y corporativas, Negocios y
 * Administración). Cambian con la pestaña, los filtros y la búsqueda.
 */
trait ShowsUpcomingRenovationCounts
{
    public function getSubheading(): string|Htmlable|null
    {
        $counts = UpcomingRenovationCounts::forQuery($this->getFilteredTableQuery());
        $colors = [15 => SummaryCards::RED, 30 => SummaryCards::AMBER, 45 => SummaryCards::BLUE];
        $cards = [];

        foreach (UpcomingRenovationCounts::WINDOWS as $days) {
            $cards[] = [
                'label' => 'Próximos '.$days.' días',
                'value' => number_format($counts[$days], 0, ',', '.'),
                'detail' => $counts[$days] === 1 ? 'renovación' : 'renovaciones',
                'color' => $colors[$days],
            ];
        }

        return SummaryCards::render($cards);
    }
}
