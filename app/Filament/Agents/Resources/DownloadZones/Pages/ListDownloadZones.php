<?php

namespace App\Filament\Agents\Resources\DownloadZones\Pages;

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

        return [
            'TODOS' => Tab::make(),
            'INSTRUMENTOS COMERCIALES' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->where('zone_id', 1))
                ->badge(DownloadZone::query()->where('zone_id', 1)->count())
                ->badgeColor('success'),
            'RECURSOS COMERCIALES' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->where('zone_id', 2))
                ->badge(DownloadZone::query()->where('zone_id', 2)->count())
                ->badgeColor('success'),
            'BIBLIOTECA DE CONDICIONES' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->where('zone_id', 3))
                ->badge(DownloadZone::query()->where('zone_id', 3)->count())
                ->badgeColor('success'),
            'ADMINISTRACION' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->where('zone_id', 4))
                ->badge(DownloadZone::query()->where('zone_id', 4)->count())
                ->badgeColor('success'),
            'PROVEEDORES' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->where('zone_id', 5))
                ->badge(DownloadZone::query()->where('zone_id', 5)->count())
                ->badgeColor('success'),
            'SERVICIOS MEDICOS' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->where('zone_id', 6))
                ->badge(DownloadZone::query()->where('zone_id', 6)->count())
                ->badgeColor('success'),
        ];
    }
}