<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Renovations\Pages;

use App\Filament\Administration\Resources\Renovations\RenovationResource;
use App\Filament\Administration\Resources\Renovations\Tables\RenovationsTable;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListRenovations extends ListRecords
{
    protected static string $resource = RenovationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return RenovationsTable::getTabs();
    }
}
