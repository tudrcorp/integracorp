<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicineListSpecialists\Pages;

use App\Filament\Operations\Resources\TelemedicineListSpecialists\TelemedicineListSpecialistResource;
use App\Support\Filament\Operations\TelemedicineClinicalCatalogForm;
use App\Support\Filament\Operations\TelemedicineClinicalCatalogPages;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTelemedicineListSpecialist extends EditRecord
{
    protected static string $resource = TelemedicineListSpecialistResource::class;

    public function getTitle(): string
    {
        $name = trim((string) ($this->getRecord()?->name ?? ''));

        return $name !== '' ? 'Editar especialista: '.$name : 'Editar especialista';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            TelemedicineClinicalCatalogPages::backAction($this->getResource()::getUrl('index')),
            TelemedicineListSpecialistResource::deleteAction(),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return TelemedicineClinicalCatalogPages::enhanceSaveAction(parent::getSaveFormAction());
    }

    protected function getCancelFormAction(): Action
    {
        return TelemedicineClinicalCatalogPages::enhanceCancelAction(parent::getCancelFormAction());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return TelemedicineClinicalCatalogForm::normalizeSpecialist($data);
    }

    protected function getSavedNotification(): ?Notification
    {
        return TelemedicineClinicalCatalogPages::savedNotification('especialista', $this->getRecord()?->name);
    }
}
