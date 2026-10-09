<?php

declare(strict_types=1);

namespace App\Filament\Shared\Renovations\Concerns;

use App\Support\Filament\SummaryCards;
use App\Support\Renovations\UpcomingRenovationCounts;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Tarjetas con la cantidad de renovaciones vencidas y de los próximos 15, 30 y
 * 45 días (tramos sin solaparse) en el listado de renovaciones (individuales y
 * corporativas, Negocios y Administración). Cambian con la pestaña, los filtros y
 * la búsqueda.
 *
 * Cada tarjeta es un botón: al tocarla aplica el filtro «Renueva en» con su tramo,
 * y al tocarla de nuevo lo quita. Requiere que la tabla tenga ese filtro.
 */
trait ShowsUpcomingRenovationCounts
{
    public function getSubheading(): string|Htmlable|null
    {
        $activeBucket = $this->activeRenewalBucket();
        $counts = $this->upcomingRenovationCountsIgnoringBucketFilter();
        $today = CarbonImmutable::today();

        $colors = [
            UpcomingRenovationCounts::OVERDUE => SummaryCards::RED,
            UpcomingRenovationCounts::DAYS_15 => '#ea580c',
            UpcomingRenovationCounts::DAYS_30 => SummaryCards::AMBER,
            UpcomingRenovationCounts::DAYS_45 => SummaryCards::BLUE,
        ];

        $cards = [];

        foreach (UpcomingRenovationCounts::BUCKETS as $bucket) {
            $isActive = $activeBucket === $bucket;
            $label = UpcomingRenovationCounts::label($bucket);

            $cards[] = [
                'label' => $label,
                'value' => number_format($counts[$bucket], 0, ',', '.'),
                'detail' => $isActive ? 'Filtrando · toca para quitar' : $this->renewalBucketDetail($bucket, $counts[$bucket], $today),
                'color' => $colors[$bucket],
                'action' => "toggleRenewalBucket('{$bucket}')",
                'active' => $isActive,
                'title' => $isActive ? 'Quitar el filtro «'.$label.'»' : 'Ver solo las renovaciones: '.mb_strtolower($label),
            ];
        }

        return SummaryCards::render($cards);
    }

    /**
     * Aplica (o quita, si ya estaba) el filtro «Renueva en» con el tramo indicado.
     */
    public function toggleRenewalBucket(string $bucket): void
    {
        if (! in_array($bucket, UpcomingRenovationCounts::BUCKETS, true)) {
            return;
        }

        $this->tableFilters[UpcomingRenovationCounts::FILTER]['tramo'] = $this->activeRenewalBucket() === $bucket ? null : $bucket;

        $this->updatedTableFilters();
    }

    protected function activeRenewalBucket(): ?string
    {
        $bucket = $this->tableFilters[UpcomingRenovationCounts::FILTER]['tramo'] ?? null;

        return in_array($bucket, UpcomingRenovationCounts::BUCKETS, true) ? $bucket : null;
    }

    /**
     * Cuenta con la pestaña, los demás filtros y la búsqueda, pero **sin** el filtro
     * de tramo: si no, al tocar una tarjeta las otras caerían a cero y dejarían de
     * servir para cambiar de tramo.
     *
     * @return array<string, int>
     */
    protected function upcomingRenovationCountsIgnoringBucketFilter(): array
    {
        $filters = $this->tableFilters;

        if (is_array($filters) && array_key_exists(UpcomingRenovationCounts::FILTER, $filters)) {
            $this->tableFilters[UpcomingRenovationCounts::FILTER]['tramo'] = null;
        }

        try {
            return UpcomingRenovationCounts::forQuery($this->getFilteredTableQuery());
        } finally {
            $this->tableFilters = $filters;
        }
    }

    protected function renewalBucketDetail(string $bucket, int $count, CarbonImmutable $today): string
    {
        $noun = $count === 1 ? 'renovación' : 'renovaciones';

        if ($bucket === UpcomingRenovationCounts::OVERDUE) {
            return $noun.' con fecha pasada';
        }

        [$from, $to] = UpcomingRenovationCounts::dates($bucket, $today);

        return $noun.' · '.$from->format('d/m').' al '.$to->format('d/m');
    }
}
