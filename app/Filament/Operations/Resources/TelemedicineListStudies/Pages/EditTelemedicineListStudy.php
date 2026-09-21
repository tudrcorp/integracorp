<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicineListStudies\Pages;

use App\Filament\Operations\Resources\TelemedicineListStudies\TelemedicineListStudyResource;
use App\Support\Filament\Operations\TelemedicineClinicalCatalogForm;
use App\Support\Filament\Operations\TelemedicineClinicalCatalogPages;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTelemedicineListStudy extends EditRecord
{
    protected static string $resource = TelemedicineListStudyResource::class;

    public function getTitle(): string
    {
        $name = trim((string) ($this->getRecord()?->name ?? ''));

        return $name !== '' ? 'Editar estudio: '.$name : 'Editar estudio';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            TelemedicineClinicalCatalogPages::backAction($this->getResource()::getUrl('index')),
            TelemedicineListStudyResource::deleteAction(),
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
        return TelemedicineClinicalCatalogForm::normalize($data);
    }

    protected function getSavedNotification(): ?Notification
    {
        return TelemedicineClinicalCatalogPages::savedNotification('estudio', $this->getRecord()?->name);
    }
}
