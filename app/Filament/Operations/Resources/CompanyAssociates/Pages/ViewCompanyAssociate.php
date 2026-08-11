<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\CompanyAssociates\Pages;

use App\Filament\Operations\Resources\CompanyAssociates\NuevosNegociosAssociateResource;
use App\Filament\Operations\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Models\CompanyAssociate;
use App\Services\AssociateCompanyAssociateWithTelemedicinePatientService;
use App\Support\Companies\CompanyAssociatePageHeader;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ViewCompanyAssociate extends ViewRecord
{
    /**
     * Misma apariencia que Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    /** Misma forma iOS que TICKET_BUTTON_CLASS pero gris (theme.css .ticket-btn-ios-gray) */
    private const TICKET_BUTTON_GRAY_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected static string $resource = NuevosNegociosAssociateResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        /** @var CompanyAssociate $record */
        $record = parent::resolveRecord($key);
        $record->loadMissing([
            'company',
            'responsible',
            'state',
            'city',
        ]);

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('associate_as_patient')
                ->label('Asociar a Pacientes')
                ->icon('heroicon-o-user-plus')
                ->color('success')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ])
                ->requiresConfirmation()
                ->modalSubmitAction(
                    fn (Action $action): Action => $action
                        ->color('success')
                        ->extraAttributes([
                            'class' => self::TICKET_BUTTON_CLASS.' min-w-[7rem] !px-6',
                        ])
                )
                ->modalCancelAction(
                    fn (Action $action): Action => $action
                        ->color('gray')
                        ->extraAttributes([
                            'class' => self::TICKET_BUTTON_GRAY_CLASS.' min-w-[7rem] !px-6',
                        ])
                )
                ->modalHeading('Asociar asociado de nuevos negocios como paciente')
                ->modalDescription(function (): string {
                    /** @var CompanyAssociate $associate */
                    $associate = $this->getRecord();

                    return 'Se registrará o actualizará el paciente de telemedicina con los datos del asociado «'
                        .($associate->full_name ?? 'Sin nombre')
                        .'». ¿Desea continuar?';
                })
                ->modalSubmitActionLabel('Sí, asociar')
                ->modalCancelActionLabel('Cancelar')
                ->action(function (): void {
                    /** @var CompanyAssociate $associate */
                    $associate = $this->getRecord();

                    try {
                        $result = AssociateCompanyAssociateWithTelemedicinePatientService::run($associate);
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('No se pudo asociar el asociado')
                            ->body(collect($exception->errors())->flatten()->first() ?? 'Revise los datos del asociado e intente de nuevo.')
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title($result['was_recently_created'] ? 'Paciente registrado' : 'Paciente actualizado')
                        ->body(
                            $result['was_recently_created']
                                ? 'El asociado de nuevos negocios se asoció como paciente de telemedicina.'
                                : 'Ya existía un paciente con ese correo; se actualizaron los datos con la información del asociado.'
                        )
                        ->success()
                        ->send();

                    $this->redirect(TelemedicinePatientResource::getUrl('view', ['record' => $result['patient']]));
                })
                ->hidden(fn (): bool => in_array('ATENMEDI', Auth::user()?->departament ?? [], true)),
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_GRAY_CLASS,
                ])
                ->url(NuevosNegociosAssociateResource::getUrl()),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        /** @var CompanyAssociate $associate */
        $associate = $this->getRecord();

        return CompanyAssociatePageHeader::make($associate, 'Nuevos Negocios');
    }
}
