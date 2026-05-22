<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Renovations\Tables;

use App\Filament\Administration\Resources\Affiliations\AffiliationResource;
use App\Filament\Administration\Resources\Renovations\RenovationResource;
use App\Filament\Shared\Renovations\RenovationsTable as SharedRenovationsTable;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Table;

class RenovationsTable
{
    /** @return array<string, Tab> */
    public static function getTabs(): array
    {
        return SharedRenovationsTable::getTabs();
    }

    public static function configure(Table $table): Table
    {
        return SharedRenovationsTable::configure(
            $table,
            RenovationResource::class,
            AffiliationResource::class,
        );
    }
}
