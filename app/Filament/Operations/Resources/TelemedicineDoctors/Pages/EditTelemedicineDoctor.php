<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicineDoctors\Pages;

use App\Filament\Operations\Resources\TelemedicineDoctors\TelemedicineDoctorResource;
use App\Models\TelemedicineDoctor;
use App\Support\Filament\FilamentIosButton;
use App\Support\Filament\TelemedicineDoctorPageHeader;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class EditTelemedicineDoctor extends EditRecord
{
    protected static string $resource = TelemedicineDoctorResource::class;

    /**
     * Mismo estilo iOS gris que cancelar modal (theme.css .ticket-btn-ios-gray).
     */
    private const TICKET_BUTTON_GRAY_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    /**
     * Misma forma iOS que primary/gris; paleta roja tipo danger (theme.css .aviso-btn-ios-danger).
     */
    private const TICKET_BUTTON_DANGER_CLASS = 'aviso-btn-ios-danger shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public function getTitle(): string|Htmlable
    {
        $doctor = $this->getRecord();

        return $doctor instanceof TelemedicineDoctor
            ? TelemedicineDoctorPageHeader::forDoctor($doctor, context: 'edit')
            : 'Editar médico';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver al directorio')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(TelemedicineDoctorResource::getUrl('index'))
                ->extraAttributes(['class' => self::TICKET_BUTTON_GRAY_CLASS], merge: true),
            Action::make('enableDoctor')
                ->label('Habilitar médico')
                ->icon('heroicon-c-user-plus')
                ->color('success')
                ->visible(fn (): bool => ! $this->doctorIsActive())
                ->requiresConfirmation()
                ->modalHeading('Habilitar médico')
                ->modalDescription(fn (): string => '¿Confirma que desea habilitar a '.$this->doctorDisplayName().'? Volverá a aparecer como activo en telemedicina.')
                ->modalSubmitActionLabel('Habilitar')
                ->modalSubmitAction(fn (Action $action) => $action->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('success'),
                ], merge: true))
                ->extraAttributes(['class' => FilamentIosButton::extraClassForFilamentColor('success')], merge: true)
                ->action(function (): void {
                    $this->updateDoctorStatus('ACTIVO');
                    Notification::make()
                        ->title('Médico habilitado')
                        ->body($this->doctorDisplayName().' quedó activo.')
                        ->success()
                        ->send();
                }),
            Action::make('disableDoctor')
                ->label('Deshabilitar médico')
                ->icon('heroicon-c-user-minus')
                ->modalIcon('heroicon-c-user-minus')
                ->color('danger')
                ->visible(fn (): bool => $this->doctorIsActive())
                ->extraAttributes(['class' => self::TICKET_BUTTON_DANGER_CLASS], merge: true)
                ->requiresConfirmation()
                ->modalHeading('Deshabilitar médico')
                ->modalDescription(fn (): string => '¿Está seguro de deshabilitar a '.$this->doctorDisplayName().'? Dejará de figurar como activo en el directorio.')
                ->modalSubmitActionLabel('Deshabilitar')
                ->modalSubmitAction(fn (Action $action) => $action->extraAttributes(['class' => self::TICKET_BUTTON_DANGER_CLASS], merge: true))
                ->action(function (): void {
                    $this->updateDoctorStatus('INACTIVO');
                    Notification::make()
                        ->title('Médico deshabilitado')
                        ->body($this->doctorDisplayName().' quedó inactivo.')
                        ->warning()
                        ->send();
                }),
        ];
    }

    private function doctorIsActive(): bool
    {
        return strtoupper(trim((string) $this->getRecord()->status)) === 'ACTIVO';
    }

    private function doctorDisplayName(): string
    {
        $name = trim((string) ($this->getRecord()->full_name ?? ''));

        return $name !== '' ? $name : 'este médico';
    }

    private function updateDoctorStatus(string $status): void
    {
        $record = $this->getRecord();
        $record->status = $status;
        $record->updated_by = Auth::user()?->name;
        $record->save();
        $record->refresh();
    }
}
