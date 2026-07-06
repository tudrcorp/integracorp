<?php

declare(strict_types=1);

use App\Filament\Business\Resources\CorporateQuoteRequests\Pages\ListCorporateQuoteRequests;
use App\Filament\Business\Resources\CorporateQuoteRequests\Widgets\CorporateQuoteRequestChannelChart;
use App\Filament\Business\Resources\CorporateQuoteRequests\Widgets\CorporateQuoteRequestCreatorsChart;
use App\Filament\Business\Resources\CorporateQuoteRequests\Widgets\StatsOverviewTotalCorporateQuoteRequest;

it('registra los widgets de resumen y graficos en el listado de solicitudes dress taylor', function (): void {
    $page = new class BusinessCorporateQuoteRequestsWidgetsRegistrationTest ListCorporateQuoteRequests
    {
        public function exposedHeaderWidgets(): array
        {
            return $this->getHeaderWidgets();
        }
    };

    expect($page->exposedHeaderWidgets())->toBe([
        StatsOverviewTotalCorporateQuoteRequest::class,
        CorporateQuoteRequestCreatorsChart::class,
        CorporateQuoteRequestChannelChart::class,
    ]);
});
