<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Sprints\Pages;

use App\Enums\ProjectManagement\SprintStatus;
use App\Filament\Projects\Resources\ProjectManagement\Sprints\SprintResource;
use App\Models\ProjectManagement\Sprint;
use App\Support\Filament\FilamentIosButton;
use App\Support\ProjectManagement\SprintLifecycle;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class ViewSprint extends ViewRecord
{
    protected static string $resource = SprintResource::class;

    protected static ?string $title = 'Detalles del sprint';

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)
            ->load('project:id,name');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('activateSprint')
                ->label('Activar sprint')
                ->icon('heroicon-s-play')
                ->color('success')
                ->visible(fn (Sprint $record): bool => $record->status === SprintStatus::Planned)
                ->requiresConfirmation()
                ->modalHeading('Activar sprint')
                ->modalDescription('Solo puede haber un sprint activo por proyecto.')
                ->action(function (Sprint $record): void {
                    try {
                        (new SprintLifecycle)->activate($record);
                        Notification::make()
                            ->title('Sprint activado')
                            ->success()
                            ->send();
                        $this->refreshFormData(['status']);
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title('No se pudo activar')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('success'),
                ]),
            Action::make('completeSprint')
                ->label('Completar sprint')
                ->icon('heroicon-s-check-circle')
                ->color('warning')
                ->visible(fn (Sprint $record): bool => in_array($record->status, [SprintStatus::Planned, SprintStatus::Active], true))
                ->requiresConfirmation()
                ->modalHeading('Completar sprint')
                ->modalDescription('Las historias no finalizadas volverán al Product Backlog.')
                ->action(function (Sprint $record): void {
                    try {
                        (new SprintLifecycle)->complete($record);
                        Notification::make()
                            ->title('Sprint completado')
                            ->success()
                            ->send();
                        $this->refreshFormData(['status']);
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title('No se pudo completar')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
                ]),
            EditAction::make()
                ->label('Editar')
                ->icon('heroicon-s-pencil')
                ->color('primary')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                ]),
        ];
    }
}
