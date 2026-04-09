<?php

namespace App\Filament\Telemedicina\Widgets;

use App\Models\TelemedicineCase;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class CaseStats extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Casos Asignados y Atendidos';

    protected ?string $description = 'Estadísticas de casos.';

    public function getSectionContentComponent(): Component
    {
        return Section::make()
            ->heading($this->getHeading())
            ->description($this->getDescription())
            ->schema($this->getCachedStats())
            ->columns($this->getColumns())
            ->contained(false)
            ->gridContainer()
            ->extraAttributes([
                'class' => 'fi-telemedicine-case-stats-ios',
            ]);
    }

    protected function getStats(): array
    {
        return [
            Stat::make('CASOS ASIGNADOS', $this->getTotalCasesAssigned())
                ->description('Estado ASIGNADO')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('warning')
                ->extraAttributes([
                    'class' => implode(' ', [
                        'fi-telemedicine-case-stat-ios',
                        'fi-telemedicine-case-stat-ios--assigned',
                        'cursor-pointer transition-[transform,box-shadow] duration-200 active:scale-[0.98]',
                    ]),
                    'wire:click' => "\$dispatch('setStatusFilter', { filter: 'processed' })",
                ]),
            Stat::make('CASOS EN SEGUIMIENTO', $this->getTotalCasesFollowUp())
                ->description('Estado EN SEGUIMIENTO')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('info')
                ->extraAttributes([
                    'class' => implode(' ', [
                        'fi-telemedicine-case-stat-ios',
                        'fi-telemedicine-case-stat-ios--followup',
                        'transition-[transform,box-shadow] duration-200 active:scale-[0.98]',
                    ]),
                ]),
            Stat::make('ALTA MEDICA', $this->getTotalCasesAttended())
                ->description('Estado ALTA MÉDICA')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->extraAttributes([
                    'class' => implode(' ', [
                        'fi-telemedicine-case-stat-ios',
                        'fi-telemedicine-case-stat-ios--discharge',
                        'transition-[transform,box-shadow] duration-200 active:scale-[0.98]',
                    ]),
                ]),
        ];
    }

    public function getColumns(): int|array
    {
        return 3;
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
        return TelemedicineCase::where('telemedicine_doctor_id', Auth::user()->doctor_id)->where('status', 'ALTA MEDICA')->count();
    }

    public function getTotalCasesFollowUp(): int
    {
        return TelemedicineCase::where('telemedicine_doctor_id', Auth::user()->doctor_id)->where('status', 'EN SEGUIMIENTO')->count();
    }
}
