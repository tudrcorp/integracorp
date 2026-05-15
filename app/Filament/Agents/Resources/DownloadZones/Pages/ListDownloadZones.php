<?php

namespace App\Filament\Agents\Resources\DownloadZones\Pages;

use App\Filament\Agents\Resources\DownloadZones\DownloadZoneResource;
use App\Models\DownloadZone;
use App\Models\Zone;
use App\Support\Filament\DownloadZoneTabIcons;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDownloadZones extends ListRecords
{
    protected static string $resource = DownloadZoneResource::class;

    protected static ?string $title = 'Zona de descarga';

    public function getTabs(): array
    {
        $tabs = [];

        $zones = Zone::query()->orderBy('position')->get();

        foreach ($zones as $zone) {
            $label = filled($zone->zone) ? $zone->zone : ($zone->code ?: 'Zona #'.$zone->id);
            $zoneId = $zone->id;

            $tabs['zone_'.$zoneId] = Tab::make($label)
                ->icon(DownloadZoneTabIcons::forZone($zone))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('zone_id', $zoneId)->orderBy('position')->orderBy('id'))
                ->badge(DownloadZone::query()->where('zone_id', $zoneId)->count())
                ->badgeColor('success');
        }

        $tabs['todos'] = Tab::make('TODOS')
            ->icon(DownloadZoneTabIcons::forTodosTab());

        return $tabs;
    }
}
