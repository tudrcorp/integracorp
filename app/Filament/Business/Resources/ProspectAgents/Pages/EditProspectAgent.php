<?php

namespace App\Filament\Business\Resources\ProspectAgents\Pages;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentDeletionActions;
use App\Filament\Business\Resources\ProspectAgents\ProspectAgentResource;
use App\Filament\Business\Resources\TravelAgencies\TravelAgencyResource;
use App\Models\ProspectAgent;
use App\Models\TravelAgency;
use App\Support\ProspectAgents\ProspectAgentToTravelAgencyConverter;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditProspectAgent extends EditRecord
{
    protected static string $resource = ProspectAgentResource::class;

    private const IOS_BUTTON_BASE = ' shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_PRIMARY_BUTTON_CLASS = 'aviso-btn-ios-primary'.self::IOS_BUTTON_BASE;

    private const IOS_DANGER_BUTTON_CLASS = 'aviso-btn-ios-danger'.self::IOS_BUTTON_BASE;

    /**
     * Un prospecto eliminado no se edita: primero hay que restaurarlo desde Ver.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var ProspectAgent $prospect */
        $prospect = $this->getRecord();

        if ($prospect->trashed()) {
            Notification::make()
                ->warning()
                ->title('Este prospecto está eliminado')
                ->body('Restáuralo para poder editarlo.')
                ->send();

            $this->redirect(ProspectAgentResource::getUrl('view', ['record' => $prospect]), navigate: true);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->label('Ver')
                ->icon('heroicon-o-eye')
                ->extraAttributes([
                    'class' => self::IOS_PRIMARY_BUTTON_CLASS,
                ]),
            ProspectAgentDeletionActions::deleteAction(ProspectAgentResource::getUrl('index'))
                ->extraAttributes([
                    'class' => self::IOS_DANGER_BUTTON_CLASS,
                ]),
        ];
    }

    protected static ?string $title = 'Formularios de edición de prospectos';

    protected function beforeSave(): void
    {
        /** @var ProspectAgent $prospect */
        $prospect = $this->getRecord();

        if ($prospect->trashed()) {
            Notification::make()
                ->danger()
                ->title('No se guardaron los cambios')
                ->body('El prospecto fue eliminado. Restáuralo para poder editarlo.')
                ->send();

            $this->halt();
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {

        $data['updated_by'] = Auth::user()->name;

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var ProspectAgent $prospect */
        $prospect = $this->getRecord()->refresh();

        if (! ProspectAgentToTravelAgencyConverter::isEligible($prospect)) {
            return;
        }

        $agency = ProspectAgentToTravelAgencyConverter::existingAgency($prospect)
            ?? ProspectAgentToTravelAgencyConverter::convertIfEligible($prospect);

        if (! $agency instanceof TravelAgency) {
            return;
        }

        Notification::make()
            ->success()
            ->title('Aliado activo en Agencias de viaje')
            ->body('El prospecto ya figura en el listado de agencias de viaje. Puedes abrir su ficha para completar IDs, comisión o crédito.')
            ->actions([
                Action::make('verAgencia')
                    ->label('Ver agencia')
                    ->url(TravelAgencyResource::getUrl('edit', ['record' => $agency]))
                    ->button(),
            ])
            ->send();
    }
}
