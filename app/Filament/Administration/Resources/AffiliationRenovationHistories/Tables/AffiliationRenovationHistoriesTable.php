<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AffiliationRenovationHistories\Tables;

use App\Filament\Administration\Resources\AffiliationRenovationHistories\AffiliationRenovationHistoryResource;
use App\Filament\Administration\Resources\Affiliations\AffiliationResource;
use App\Filament\Shared\RenovationHistories\RenovationHistoriesTable as SharedRenovationHistoriesTable;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Table;

class AffiliationRenovationHistoriesTable
{
    /** @return array<string, Tab> */
    public static function getTabs(): array
    {
        return SharedRenovationHistoriesTable::getTabs();
    }

    public static function configure(Table $table): Table
    {
        return SharedRenovationHistoriesTable::configure(
            $table,
            AffiliationRenovationHistoryResource::class,
            AffiliationResource::class,
        );
    }
}
