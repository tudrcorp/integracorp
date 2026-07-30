<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AffiliationCorporateRenovationHistories\Pages;

use App\Filament\Administration\Resources\AffiliationCorporateRenovationHistories\AffiliationCorporateRenovationHistoryResource;
use App\Filament\Administration\Resources\AffiliationCorporateRenovationHistories\Tables\AffiliationCorporateRenovationHistoriesTable;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListAffiliationCorporateRenovationHistories extends ListRecords
{
    protected static string $resource = AffiliationCorporateRenovationHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return AffiliationCorporateRenovationHistoriesTable::getTabs();
    }
}
