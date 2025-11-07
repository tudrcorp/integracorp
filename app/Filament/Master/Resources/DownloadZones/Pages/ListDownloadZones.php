<?php

namespace App\Filament\Master\Resources\DownloadZones\Pages;

use App\Models\DownloadZone;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Master\Resources\DownloadZones\DownloadZoneResource;

class ListDownloadZones extends ListRecords
{
    protected static string $resource = DownloadZoneResource::class;

    protected static ?string $title = 'Zona de Descargas';

    public function getTabs(): array
    {

        return [
            'METODOS DE PAGO' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->where('zone_id', 4))
                ->badge(DownloadZone::query()->where('zone_id', 4)->count())
                ->badgeColor('success'),
            'RECURSOS DEL AGENTE' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->where('zone_id', 1))
                ->badge(DownloadZone::query()->where('zone_id', 1)->count())
                ->badgeColor('success'),
            'TU DR. EN CASA' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->where('zone_id', 3))
                ->badge(DownloadZone::query()->where('zone_id', 3)->count())
                ->badgeColor('success'),
            'TU DR. EN VIAJES' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->where('zone_id', 2))
                ->badge(DownloadZone::query()->where('zone_id', 2)->count())
                ->badgeColor('success'),
            'TODOS' => Tab::make(),
        ];
    }
}