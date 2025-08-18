<?php

namespace App\Filament\Telemedicina\Widgets;

use App\Models\TelemedicineCase;
use Illuminate\Support\Facades\Auth;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CaseStats extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Casos Asignados y Atendidos';

    protected ?string $description = 'Estadísticas de casos.';
    
    protected function getStats(): array
    {
        return [
            Stat::make('CASOS ASIGNADOS', $this->getTotalCasesAssigned())
                ->description('32k increase')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('CASOS ATENDIDOS', $this->getTotalCasesAttended())
                ->description('32k increase')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
        ];
    }

    public function getColumns(): int | array
    {
        return 2;
    }

    public function getTotalCases(): int
    {
        return TelemedicineCase::where('telemedicine_doctor_id', Auth::user()->doctor_id)->count();
    }

    public function getTotalCasesAssigned(): int
    {
        return TelemedicineCase::where('telemedicine_doctor_id', Auth::user()->doctor_id)->where('status', 'ASIGNADO')->count();
    }

    public function getTotalCasesAttended(): int
    {
        return TelemedicineCase::where('telemedicine_doctor_id', Auth::user()->doctor_id)->where('status', 'ATENDIDO')->count();
    }   
}