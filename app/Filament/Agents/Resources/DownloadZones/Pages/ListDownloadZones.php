<?php

namespace App\Filament\Agents\Resources\DownloadZones\Pages;

use App\Models\Zone;
use App\Models\DownloadZone;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Agents\Resources\DownloadZones\DownloadZoneResource;

class ListDownloadZones extends ListRecords
{
    protected static string $resource = DownloadZoneResource::class;

    protected static ?string $title = 'Zona de descarga';


    public function getTabs(): array
    {
        return Zone::all()->map(function ($zone) {
            return Tab::make($zone->zone)
                ->modifyQueryUsing(fn(Builder $query) => $query->where('zone_id', $zone->id))
                ->badge(DownloadZone::query()->where('zone_id', $zone->id)->count())
                ->badgeColor('success');
        })->toArray();
    }
}