<?php

namespace App\Filament\Telemedicina\Widgets;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineDoctor;
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
        // dd($this->getTotalCasesTransportAmbulance());
        return [
            Stat::make('CASOS ASIGNADOS', $this->getTotalCasesAssigned())
                ->description('ASIGNADO')
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
                ->description('EN SEGUIMIENTO')
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
                ->description('ALTA MÉDICA')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->extraAttributes([
                    'class' => implode(' ', [
                        'fi-telemedicine-case-stat-ios',
                        'fi-telemedicine-case-stat-ios--discharge',
                        'transition-[transform,box-shadow] duration-200 active:scale-[0.98]',
                    ]),
                ]),
            Stat::make('TRASLADO EN AMBULANCIA', $this->getTotalCasesTransportAmbulance())
                ->description('TRASLADO EN AMBULANCIA')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('danger')
                ->extraAttributes([
                    'class' => implode(' ', [
                        'fi-telemedicine-case-stat-ios',
                        'fi-telemedicine-case-stat-ios--transport-ambulance',
                        'transition-[transform,box-shadow] duration-200 active:scale-[0.98]',
                    ]),
                ])
                ->visible(fn () => TelemedicineDoctor::where('id', Auth::user()->doctor_id)->first()->managed_by == 'ATENMEDI'),
        ];
    }

    public function getColumns(): int|array
    {
        if (TelemedicineDoctor::where('id', Auth::user()->doctor_id)->first()->managed_by == 'ATENMEDI') {
            return 4;
        }

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

    public function getTotalCasesFollowUp(): int
    {
        return TelemedicineCase::where('telemedicine_doctor_id', Auth::user()->doctor_id)->where('status', 'EN SEGUIMIENTO')->count();
    }

    public function getTotalCasesAttended(): int
    {
        return TelemedicineCase::where('telemedicine_doctor_id', Auth::user()->doctor_id)->where('status', 'ALTA MEDICA')->count();
    }

    /**
     * Cantidad de casos donde el servicio devuelto es diferente a 'TRASLADO EN AMBULANCIA'
     */
    public function getTotalCasesTransportAmbulance(): int
    {
        return TelemedicineCase::where('doctor_id_first_accompaniment', Auth::user()->doctor_id)->count();
    }
}
