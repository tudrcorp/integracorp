<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Affiliations\Pages;

use App\Filament\Administration\Resources\Affiliations\AffiliationResource;
use App\Filament\Administration\Resources\Affiliations\Tables\AffiliationsTable;
use App\Filament\Administration\Resources\Affiliations\Widgets\StatsOverview;
use Filament\Resources\Pages\ListRecords;

class ListAffiliations extends ListRecords
{
    protected static string $resource = AffiliationResource::class;

    protected static ?string $title = 'Afiliaciones individuales';

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return AffiliationsTable::getTabs();
    }
}
