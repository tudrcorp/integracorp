<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AffiliationCorporateRenovationHistories\Tables;

use App\Filament\Administration\Resources\AffiliationCorporateRenovationHistories\AffiliationCorporateRenovationHistoryResource;
use App\Filament\Administration\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Shared\RenovationCorporateHistories\RenovationCorporateHistoriesTable as SharedRenovationCorporateHistoriesTable;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Table;

class AffiliationCorporateRenovationHistoriesTable
{
    /** @return array<string, Tab> */
    public static function getTabs(): array
    {
        return SharedRenovationCorporateHistoriesTable::getTabs();
    }

    public static function configure(Table $table): Table
    {
        return SharedRenovationCorporateHistoriesTable::configure(
            $table,
            AffiliationCorporateRenovationHistoryResource::class,
            AffiliationCorporateResource::class,
        );
    }
}
