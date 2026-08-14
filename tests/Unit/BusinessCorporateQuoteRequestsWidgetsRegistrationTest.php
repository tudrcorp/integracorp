<?php

declare(strict_types=1);

use App\Filament\Business\Resources\CorporateQuoteRequests\Pages\ListCorporateQuoteRequests;
use App\Filament\Business\Resources\CorporateQuoteRequests\Widgets\CorporateQuoteRequestChannelChart;
use App\Filament\Business\Resources\CorporateQuoteRequests\Widgets\CorporateQuoteRequestsByAgencyTable;
use App\Filament\Business\Resources\CorporateQuoteRequests\Widgets\CorporateQuoteRequestsByAgentTable;
use App\Filament\Business\Resources\CorporateQuoteRequests\Widgets\StatsOverviewTotalCorporateQuoteRequest;

it('registra los widgets de resumen, ranking y canal en el listado de solicitudes dress taylor', function (): void {
    $page = new class extends ListCorporateQuoteRequests
    {
        public function exposedHeaderWidgets(): array
        {
            return $this->getHeaderWidgets();
        }

        public function exposedHeaderWidgetsColumns(): int|array
        {
            return $this->getHeaderWidgetsColumns();
        }
    };

    expect($page->exposedHeaderWidgets())->toBe([
        StatsOverviewTotalCorporateQuoteRequest::class,
        CorporateQuoteRequestsByAgencyTable::class,
        CorporateQuoteRequestsByAgentTable::class,
        CorporateQuoteRequestChannelChart::class,
    ]);

    expect($page->exposedHeaderWidgetsColumns())->toBe([
        'default' => 1,
        'lg' => 2,
    ]);
});
