<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AffiliationCorporates\Pages;

use App\Filament\Administration\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Administration\Resources\AffiliationCorporates\Tables\AffiliationCorporatesTable;
use App\Filament\Administration\Resources\AffiliationCorporates\Widgets\StatsOverview;
use Filament\Resources\Pages\ListRecords;

class ListAffiliationCorporates extends ListRecords
{
    protected static string $resource = AffiliationCorporateResource::class;

    protected static ?string $title = 'Afiliaciones corporativas';

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return AffiliationCorporatesTable::getTabs();
    }
}
