<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\RenovationCorporates\Pages;

use App\Filament\Administration\Resources\RenovationCorporates\RenovationCorporateResource;
use App\Filament\Administration\Resources\RenovationCorporates\Tables\RenovationsCorporateTable;
use App\Filament\Concerns\ReviewsTableSelection;
use App\Filament\Shared\Renovations\Concerns\ShowsUpcomingRenovationCounts;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListRenovationCorporates extends ListRecords
{
    use ReviewsTableSelection;
    use ShowsUpcomingRenovationCounts;

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

    /**
     * @return array{singular: string, plural: string}
     */
    protected function selectionReviewNouns(): array
    {
        return ['singular' => 'renovación corporativa', 'plural' => 'renovaciones corporativas'];
    }
}
