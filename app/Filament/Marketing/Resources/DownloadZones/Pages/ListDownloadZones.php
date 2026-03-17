<?php

namespace App\Filament\Marketing\Resources\DownloadZones\Pages;

use App\Filament\Marketing\Resources\DownloadZones\DownloadZoneResource;
use App\Models\DownloadZone;
use App\Models\Zone;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDownloadZones extends ListRecords
{
    protected static string $resource = DownloadZoneResource::class;

    protected static ?string $title = 'Zona de descarga';

    protected static ?string $subtitle = 'Aquí puedes gestionar los recursos disponibles para los agentes';

    public function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(DownloadZoneResource::getUrl()),
            CreateAction::make()
                ->label('Cargar Documento')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [];

        $zones = Zone::query()->orderBy('position')->get();

        foreach ($zones as $zone) {
            $label = filled($zone->zone) ? $zone->zone : ($zone->code ?: 'Zona #' . $zone->id);
            $zoneId = $zone->id;

            $tabs['zone_' . $zoneId] = Tab::make($label)
                ->modifyQueryUsing(fn(Builder $query) => $query->where('zone_id', $zoneId))
                ->badge(DownloadZone::query()->where('zone_id', $zoneId)->count())
                ->badgeColor('success');
        }

        $tabs['todos'] = Tab::make('TODOS');

        return $tabs;
    }
}
