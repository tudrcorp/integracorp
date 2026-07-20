<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Epics\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Epics\EpicResource;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewEpic extends ViewRecord
{
    protected static string $resource = EpicResource::class;

    protected static ?string $title = 'Detalles de la épica';

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)
            ->load('project:id,name')
            ->loadCount('activities')
            ->loadSum('activities', 'story_points');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Editar')
                ->icon('heroicon-s-pencil')
                ->color('success')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('success'),
                ]),
        ];
    }
}
