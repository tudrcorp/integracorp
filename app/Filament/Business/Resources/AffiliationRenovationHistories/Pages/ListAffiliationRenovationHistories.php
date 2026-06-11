<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\AffiliationRenovationHistories\Pages;

use App\Filament\Business\Resources\AffiliationRenovationHistories\AffiliationRenovationHistoryResource;
use App\Filament\Business\Resources\AffiliationRenovationHistories\Tables\AffiliationRenovationHistoriesTable;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListAffiliationRenovationHistories extends ListRecords
{
    protected static string $resource = AffiliationRenovationHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return AffiliationRenovationHistoriesTable::getTabs();
    }
}
