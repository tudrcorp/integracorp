<?php

namespace App\Filament\Resources\DownloadZones\Pages;

use App\Filament\Resources\DownloadZones\DownloadZoneResource;
use App\Models\DownloadZone;
use App\Support\Filament\DownloadZoneTabIcons;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDownloadZones extends ListRecords
{
    protected static string $resource = DownloadZoneResource::class;

    protected static ?string $title = 'DESCARGA DE ARCHIVOS';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Cargar archivo')
                ->icon('heroicon-m-cloud-arrow-up'),
        ];
    }

    public function getTabs(): array
    {

        return [
            'METODOS DE PAGO' => Tab::make()
                ->icon(DownloadZoneTabIcons::forLabel('METODOS DE PAGO', 4))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('zone_id', 4))
                ->badge(DownloadZone::query()->where('zone_id', 4)->count())
                ->badgeColor('success'),
            'RECURSOS DEL AGENTE' => Tab::make()
                ->icon(DownloadZoneTabIcons::forLabel('RECURSOS DEL AGENTE', 1))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('zone_id', 1))
                ->badge(DownloadZone::query()->where('zone_id', 1)->count())
                ->badgeColor('success'),
            'TU DR. EN CASA' => Tab::make()
                ->icon(DownloadZoneTabIcons::forLabel('TU DR. EN CASA', 3))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('zone_id', 3))
                ->badge(DownloadZone::query()->where('zone_id', 3)->count())
                ->badgeColor('success'),
            'TU DR. EN VIAJES' => Tab::make()
                ->icon(DownloadZoneTabIcons::forLabel('TU DR. EN VIAJES', 2))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('zone_id', 2))
                ->badge(DownloadZone::query()->where('zone_id', 2)->count())
                ->badgeColor('success'),
            'TODOS' => Tab::make()
                ->icon(DownloadZoneTabIcons::forTodosTab()),
        ];
    }
}
