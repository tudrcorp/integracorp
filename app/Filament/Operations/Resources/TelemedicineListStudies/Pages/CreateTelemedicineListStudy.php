<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicineListStudies\Pages;

use App\Filament\Operations\Resources\TelemedicineListStudies\TelemedicineListStudyResource;
use App\Support\Filament\Operations\TelemedicineClinicalCatalogForm;
use App\Support\Filament\Operations\TelemedicineClinicalCatalogPages;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateTelemedicineListStudy extends CreateRecord
{
    protected static string $resource = TelemedicineListStudyResource::class;

    protected static ?string $title = 'Crear estudio';

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            TelemedicineClinicalCatalogPages::backAction($this->getResource()::getUrl('index')),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    protected function getCreateFormAction(): Action
    {
        return TelemedicineClinicalCatalogPages::enhanceCreateAction(
            parent::getCreateFormAction(),
            'Guardar estudio',
        );
    }

    protected function getCancelFormAction(): Action
    {
        return TelemedicineClinicalCatalogPages::enhanceCancelAction(parent::getCancelFormAction());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return TelemedicineClinicalCatalogForm::normalize($data);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return TelemedicineClinicalCatalogPages::createdNotification('estudio', $this->getRecord()?->name);
    }
}
