<?php

namespace App\Filament\Operations\Resources\TelemedicinePatients\Pages;

use App\Filament\Operations\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Models\TelemedicinePatient;
use App\Support\Filament\TelemedicinePatientPageHeader;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class EditTelemedicinePatient extends EditRecord
{
    protected static string $resource = TelemedicinePatientResource::class;

    /**
     * Mismo estilo iOS gris que cancelar modal (theme.css .ticket-btn-ios-gray).
     */
    private const TICKET_BUTTON_GRAY_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    /**
     * Misma forma iOS que primary/gris; paleta roja tipo danger (theme.css .aviso-btn-ios-danger).
     */
    private const TICKET_BUTTON_DANGER_CLASS = 'aviso-btn-ios-danger shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function resolveRecord(int|string $key): Model
    {
        /** @var TelemedicinePatient $record */
        $record = parent::resolveRecord($key);
        $record->loadMissing(['plan:id,description']);

        return $record;
    }

    public function getTitle(): string|Htmlable
    {
        $patient = $this->getRecord();

        return $patient instanceof TelemedicinePatient
            ? TelemedicinePatientPageHeader::forPatient($patient, context: 'edit')
            : 'Editar paciente';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver a la ficha')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_GRAY_CLASS,
                ])
                ->url(fn (): string => TelemedicinePatientResource::getUrl('view', ['record' => $this->getRecord()])),
            DeleteAction::make()
                ->label('Eliminar paciente')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_DANGER_CLASS,
                ]),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return TelemedicinePatientResource::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        $name = trim((string) ($this->getRecord()?->full_name ?? ''));

        return Notification::make()
            ->success()
            ->icon('heroicon-o-check-circle')
            ->title('Paciente actualizado')
            ->body($name !== ''
                ? 'Se guardaron los cambios de '.$name.'. Volvió al listado de pacientes.'
                : 'Los cambios del paciente ya están guardados. Volvió al listado.');
    }
}
