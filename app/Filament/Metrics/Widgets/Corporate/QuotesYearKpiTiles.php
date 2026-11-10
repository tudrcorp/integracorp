<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateQuoteMetrics;
use Carbon\CarbonImmutable;

/**
 * Cotizaciones del año en curso (1 de enero a hoy) contra el mismo tramo del año anterior.
 * No depende del filtro de la página: siempre compara años.
 */
class QuotesYearKpiTiles extends CorporateKpiTiles
{
    protected function tiles(): array
    {
        $data = CorporateQuoteMetrics::yearToDate();
        $current = $data['current'];
        $previous = $data['previous'];
        $full = $data['previous_full_year'];
        $previousYear = $data['year'] - 1;
        $note = 'Año '.$previousYear.' completo: ';

        return [
            self::tile('quotes_ytd', 'Cotizaciones '.$data['year'], $current['total'], $previous['total'], 'heroicon-o-document-duplicate', 'Cotizaciones individuales y corporativas creadas del 1 de enero a hoy, frente al mismo tramo de '.$previousYear.'.', [], note: $note.number_format($full['total'], 0, ',', '.')),
            self::tile('quotes_ytd_individual', 'Individuales '.$data['year'], $current['individual'], $previous['individual'], 'heroicon-o-user', 'Cotizaciones individuales creadas del 1 de enero a hoy, frente al mismo tramo de '.$previousYear.'.', [], note: $note.number_format($full['individual'], 0, ',', '.')),
            self::tile('quotes_ytd_corporate', 'Corporativas '.$data['year'], $current['corporate'], $previous['corporate'], 'heroicon-o-building-office', 'Cotizaciones corporativas creadas del 1 de enero a hoy, frente al mismo tramo de '.$previousYear.'.', [], note: $note.number_format($full['corporate'], 0, ',', '.')),
        ];
    }

    protected function context(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $data = CorporateQuoteMetrics::yearToDate();
        $range = self::rangeLabel($data['from'], $data['to']);
        $previousRange = self::rangeLabel($data['previous_from'], $data['previous_to']);

        return [
            ...parent::getViewData(),
            'team' => [
                ['label' => 'frente al '.$previousRange, 'value' => 0, 'display' => 'Del '.$range, 'icon' => 'heroicon-o-calendar-days'],
            ],
            'teamLabel' => 'Cotizaciones del año',
            'tilesGrid' => 'md:grid-cols-3',
            'compare' => true,
            'previousLabel' => $previousRange,
            'periodKey' => 'ytd-'.$data['to'],
        ];
    }

    private static function rangeLabel(string $from, string $to): string
    {
        $start = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);

        return $start->translatedFormat('j M').' al '.$end->translatedFormat('j M Y');
    }
}
