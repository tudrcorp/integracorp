<?php

namespace App\Filament\Operations\Resources\TelemedicineHistoryPatients\Pages;

use App\Filament\Operations\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EditTelemedicineHistoryPatient extends EditRecord
{
    protected static string $resource = TelemedicineHistoryPatientResource::class;

    protected static ?string $title = 'Editar Historia Clinica';

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
            ->label('Eliminar Historia Clinica')
            ->requiresConfirmation()
            ->modalHeading('Eliminar Historia Clinica')
            ->modalDescription('¿Está seguro de que desea eliminar esta historia clinica?')
            ->modalSubmitActionLabel('Eliminar')
            ->modalCancelActionLabel('Cancelar')
            ->action(function ($record) {
                Log::info('OPERACIONES: El usuario ' . Auth::user()->name . ' ha eliminado la historia clinica ' . $record->code);
                $record->delete();
            }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Historia Clinica actualizada')
            ->body('La historia clinica ha sido actualizada correctamente.');
    }

    protected function afterSave(): void
    {
        try {
            $this->record->update([
                'updated_by' => Auth::user()->name,
            ]);
            Log::info('OPERACIONES: El usuario ' . Auth::user()->name . ' ha actualizado la historia clinica ' . $this->record->code);

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al actualizar la historia clinica')
                ->danger()
                ->send();
            Log::error('OPERACIONES: El usuario ' . Auth::user()->name . ' ha tenido un error al actualizar la historia clinica ' . $this->record->code . ' ' . $e->getMessage());
        }
    }
}
