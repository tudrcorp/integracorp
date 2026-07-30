<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\RenovationCorporates\Tables;

use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Business\Resources\RenovationCorporates\RenovationCorporateResource;
use App\Filament\Shared\RenovationCorporates\RenovationsCorporateTable as SharedRenovationsCorporateTable;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Table;

class RenovationsCorporateTable
{
    /** @return array<string, Tab> */
    public static function getTabs(): array
    {
        return SharedRenovationsCorporateTable::getTabs();
    }

    public static function configure(Table $table): Table
    {
        return SharedRenovationsCorporateTable::configure(
            $table,
            RenovationCorporateResource::class,
            AffiliationCorporateResource::class,
            exportRouteName: 'business.renovation-corporates.export-csv',
        );
    }
}
