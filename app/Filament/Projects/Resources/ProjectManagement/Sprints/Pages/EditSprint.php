<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Sprints\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Sprints\SprintResource;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSprint extends EditRecord
{
    protected static string $resource = SprintResource::class;

    protected static ?string $title = 'Editar sprint';

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->label('Ver')
                ->icon('heroicon-s-eye')
                ->color('gray')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                ]),
            DeleteAction::make()
                ->label('Eliminar')
                ->icon('heroicon-s-trash')
                ->color('danger')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('danger'),
                ]),
        ];
    }
}
