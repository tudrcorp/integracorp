<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CompanyAssociates\Pages;

use App\Filament\Business\Resources\CompanyAssociates\CompanyAssociateResource;
use App\Jobs\RegenerateCompanyAssociateCarnetAfterEditJob;
use App\Models\CompanyAssociate;
use App\Support\Companies\CompanyAssociateRegistrar;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Throwable;

class EditCompanyAssociate extends EditRecord
{
    protected static string $resource = CompanyAssociateResource::class;

    protected static ?string $title = 'Editar asociado';

    protected bool $carnetRegenerationQueued = false;

    protected bool $carnetRegenerationFailed = false;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->label('Volver a la ficha')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['full_name'] = trim((string) ($data['full_name'] ?? ''));
        $data['identity_card'] = CompanyAssociateRegistrar::normalizeIdentityCard(
            is_string($data['identity_card'] ?? null) ? $data['identity_card'] : null,
        );
        $data['phone'] = CompanyAssociateRegistrar::normalizeInternationalPhone(
            is_string($data['phone'] ?? null) ? $data['phone'] : null,
        );
        $data['age'] = CompanyAssociateRegistrar::calculateAge(
            is_string($data['birth_date'] ?? null) ? $data['birth_date'] : null,
        );

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof CompanyAssociate || $record->isAnnulled() || ! $record->wasChanged()) {
            return;
        }

        try {
            RegenerateCompanyAssociateCarnetAfterEditJob::dispatch((int) $record->getKey());
            $this->carnetRegenerationQueued = true;
        } catch (Throwable $throwable) {
            report($throwable);
            $this->carnetRegenerationFailed = true;
        }
    }

    protected function getSavedNotification(): ?Notification
    {
        if ($this->carnetRegenerationFailed) {
            return Notification::make()
                ->warning()
                ->title('Asociado actualizado')
                ->body('Los datos quedaron guardados, pero no se pudo encolar la regeneración del carnet. Intente guardar de nuevo.');
        }

        if (! $this->carnetRegenerationQueued) {
            return Notification::make()
                ->success()
                ->title('Sin cambios')
                ->body('No hubo datos nuevos, así que el carnet no se regeneró.');
        }

        return Notification::make()
            ->success()
            ->title('Asociado actualizado')
            ->body('El carnet se está regenerando y se reenviará al asociado por correo y WhatsApp.');
    }

    protected function getRedirectUrl(): string
    {
        return CompanyAssociateResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
