<?php

namespace App\Filament\Operations\Resources\TelemedicinePatients\Pages;

use App\Filament\Operations\Concerns\AppliesOperationsAddressFromMaps;
use App\Filament\Operations\Resources\TelemedicinePatients\Actions\AssignDoctorAction;
use App\Filament\Operations\Resources\TelemedicinePatients\Actions\RegisterTpaRetailServicesAction;
use App\Filament\Operations\Resources\TelemedicinePatients\Actions\ReportSiniestralidadAction;
use App\Filament\Operations\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Models\TelemedicinePatient;
use App\Support\Filament\FilamentIosActionsMenu;
use App\Support\Filament\TelemedicinePatientPageHeader;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View as ViewContract;

class ViewTelemedicinePatient extends ViewRecord
{
    use AppliesOperationsAddressFromMaps;

    protected static string $resource = TelemedicinePatientResource::class;

    public function getFooter(): ?ViewContract
    {
        return view('filament.operations.shared.location-maps-loader');
    }

    public function getTitle(): string|Htmlable
    {
        $patient = $this->getRecord();

        return $patient instanceof TelemedicinePatient
            ? TelemedicinePatientPageHeader::forPatient($patient)
            : 'Ficha del paciente';
    }

    /**
     * Mismo estilo iOS gris que cancelar modal (theme.css .ticket-btn-ios-gray).
     */
    private const TICKET_BUTTON_GRAY_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    /**
     * Misma forma iOS que primary/gris; paleta roja tipo danger (theme.css .aviso-btn-ios-danger).
     */
    private const TICKET_BUTTON_DANGER_CLASS = 'aviso-btn-ios-danger shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    /**
     * Las acciones de la ficha van agrupadas en el menú iOS «Acciones», igual
     * que en el asistente de consulta de telemedicina.
     */
    protected function getHeaderActions(): array
    {
        return [
            FilamentIosActionsMenu::make([
                AssignDoctorAction::make(),
                RegisterTpaRetailServicesAction::make(),
                ReportSiniestralidadAction::make()
                    ->extraAttributes([]),
                EditAction::make()
                    ->label('Editar Paciente')
                    ->icon('heroicon-o-pencil')
                    ->color('primary'),
            ]),
        ];
    }
}
