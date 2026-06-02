<?php

declare(strict_types=1);

use App\Filament\Business\Resources\CorporateQuotes\Pages\ListCorporateQuotes;
use App\Filament\Business\Resources\CorporateQuotes\Widgets\StatsOverviewCorporateQuote;
use App\Filament\Business\Resources\CorporateQuotes\Widgets\StatsOverviewTotalCorporateQuote;
use App\Filament\Business\Resources\CorporateQuotes\Widgets\TotalCorporateQuoteChart;

it('registra los widgets de resumen y grafico en el listado de cotizaciones corporativas', function (): void {
    $page = new class extends ListCorporateQuotes
    {
        public function exposedHeaderWidgets(): array
        {
            return $this->getHeaderWidgets();
        }
    };

    expect($page->exposedHeaderWidgets())->toBe([
        StatsOverviewTotalCorporateQuote::class,
        StatsOverviewCorporateQuote::class,
        TotalCorporateQuoteChart::class,
    ]);
});
