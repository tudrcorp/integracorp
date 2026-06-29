<?php

namespace App\Filament\Operations\Resources\Affiliates\Pages;

use App\Filament\Operations\Concerns\AppliesOperationsAddressFromMaps;
use App\Filament\Operations\Resources\Affiliates\AffiliateResource;
use App\Filament\Operations\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Models\Affiliate;
use App\Services\AssociateAffiliateWithTelemedicinePatientService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ViewAffiliate extends ViewRecord
{
    use AppliesOperationsAddressFromMaps;

    /**
     * Misma apariencia que Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    /** Misma forma iOS que TICKET_BUTTON_CLASS pero gris (theme.css .ticket-btn-ios-gray) */
    private const TICKET_BUTTON_GRAY_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected static string $resource = AffiliateResource::class;

    public function getFooter(): ?ViewContract
    {
        return view('filament.operations.shared.location-maps-loader');
    }

    // protected static ?string $title = 'Información Detallada del Afiliado';

    protected function resolveRecord(int|string $key): Model
    {
        /** @var Affiliate $record */
        $record = parent::resolveRecord($key);
        $record->loadMissing(['affiliation.billingCollections']);

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
                ->modalHeading('Asociar afiliado como paciente')
                ->modalDescription(function (): string {
                    /** @var Affiliate $affiliate */
                    $affiliate = $this->getRecord();

                    return 'Se registrará o actualizará el paciente de telemedicina con los datos del afiliado «'
                        .$affiliate->full_name
                        .'». ¿Desea continuar?';
                })
                ->modalSubmitActionLabel('Sí, asociar')
                ->modalCancelActionLabel('Cancelar')
                ->action(function (): void {
                    /** @var Affiliate $affiliate */
                    $affiliate = $this->getRecord();

                    try {
                        $result = AssociateAffiliateWithTelemedicinePatientService::run($affiliate);
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('No se pudo asociar el afiliado')
                            ->body(collect($exception->errors())->flatten()->first() ?? 'Revise los datos del afiliado e intente de nuevo.')
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title($result['was_recently_created'] ? 'Paciente registrado' : 'Paciente actualizado')
                        ->body(
                            $result['was_recently_created']
                                ? 'El afiliado se asoció como paciente de telemedicina.'
                                : 'Ya existía un paciente con ese correo; se actualizaron los datos con la afiliación del afiliado.'
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
                ->url(AffiliateResource::getUrl()),
        ];
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $affiliate = $this->getRecord();

        // Definimos el nombre del afiliado de forma segura
        $fullName = $affiliate->full_name ?? 'Sin Nombre';

        return new \Illuminate\Support\HtmlString(
            '<div style="display: flex; flex-direction: column; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; gap: 2px; padding: 12px 0;">'.
                // Título Principal Resaltado
                '<span class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-gray-100 mb-2 dark:text-white">'.
                'Ficha del Afiliado'.
                '</span>'.

                // Subtítulo (Nombre del Paciente)
                '<span class="text-3xl font-bold tracking-tight text-gray-900 dark:text-gray-100 mb-2 dark:text-white">'.
                $fullName.
                '</span>'.

                // Estatus Estilo Badge iOS Resaltado
                '<div style="display: flex; align-items: center; margin-top: 8px;">'.
                '<span style="'.
                'background-color: #28cd41; '. // Verde iOS vibrante
                'color: #ffffff; '.
                'padding: 6px 16px; '.
                'border-radius: 50px; '.
                'font-size: 0.8rem; '.
                'font-weight: 700; '.
                'display: inline-flex; '.
                'align-items: center; '.
                'gap: 6px; '.
                'box-shadow: 0 4px 12px rgba(40, 205, 65, 0.35); '.
                'border: 1px solid rgba(255, 255, 255, 0.2);'.
                '">'.
                '<span style="font-size: 10px;">●</span> ACTIVO'.
                '</span>'.
                '</div>'.
                '</div>'
        );
    }
}
