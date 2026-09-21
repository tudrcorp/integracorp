<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicineListLaboratories\Pages;

use App\Filament\Operations\Resources\TelemedicineListLaboratories\TelemedicineListLaboratoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTelemedicineListLaboratories extends ListRecords
{
    protected static string $resource = TelemedicineListLaboratoryResource::class;

    protected static ?string $title = 'Lista de Laboratorios';

    private const TICKET_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear laboratorio')
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
        ];
    }
}
