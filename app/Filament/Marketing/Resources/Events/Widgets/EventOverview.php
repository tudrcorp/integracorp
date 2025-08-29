<?php

namespace App\Filament\Marketing\Resources\Events\Widgets;

use App\Models\Guest;
use Illuminate\Database\Eloquent\Model;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EventOverview extends StatsOverviewWidget
{
    public ?Model $record = null;
    
    protected function getStats(): array
    {
        $total_guest = Guest::where('event_id', $this->record->id)->count();
        $porcen = $total_guest * 100 / $this->record->total_guest;
        
        return [
            Stat::make('EVENTO', $this->record->title)
                ->description($this->record->status)
                ->descriptionIcon('fontisto-check')
                ->color('success'),
            Stat::make('INVITACIONES APROBADAS', $this->record->total_guest)
                ->description('Personas Invitadas')
                ->descriptionIcon('fontisto-check')
                ->color('warning'),
            Stat::make('INVITADOS CONFIRMADOS', $total_guest)
                ->description($porcen. '% Confirmados')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
        ];
    }
}