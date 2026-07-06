<?php

namespace App\Filament\Operations\Resources\TelemedicinePatients\Pages;

use App\Filament\Operations\Concerns\AppliesOperationsAddressFromMaps;
use App\Filament\Operations\Resources\TelemedicinePatients\Actions\AssignDoctorAction;
use App\Filament\Operations\Resources\TelemedicinePatients\Actions\RegisterTpaRetailServicesAction;
use App\Filament\Operations\Resources\TelemedicinePatients\TelemedicinePatientResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\View\View as ViewContract;

class ViewTelemedicinePatient extends ViewRecord
{
    use AppliesOperationsAddressFromMaps;

    protected static string $resource = TelemedicinePatientResource::class;

    public function getFooter(): ?ViewContract
    {
        return view('filament.operations.shared.location-maps-loader');
    }

    protected static ?string $title = 'Ficha del Paciente';

    /**
     * Mismo estilo iOS gris que cancelar modal (theme.css .ticket-btn-ios-gray).
     */
    private const TICKET_BUTTON_GRAY_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    /**
     * Misma forma iOS que primary/gris; paleta roja tipo danger (theme.css .aviso-btn-ios-danger).
     */
    private const TICKET_BUTTON_DANGER_CLASS = 'aviso-btn-ios-danger shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            AssignDoctorAction::make()
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
            RegisterTpaRetailServicesAction::make()
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
            EditAction::make()
                ->label('Editar Paciente')
                ->icon('heroicon-o-pencil')
                ->color('primary')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
        ];
    }
}
