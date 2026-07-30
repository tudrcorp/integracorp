<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\RenovationCorporates\Pages;

use App\Filament\Administration\Resources\RenovationCorporates\RenovationCorporateResource;
use App\Filament\Administration\Resources\RenovationCorporates\Tables\RenovationsCorporateTable;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListRenovationCorporates extends ListRecords
{
    protected static string $resource = RenovationCorporateResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return RenovationsCorporateTable::getTabs();
    }
}
