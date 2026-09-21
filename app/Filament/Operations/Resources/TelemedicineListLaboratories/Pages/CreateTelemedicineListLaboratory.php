<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicineListLaboratories\Pages;

use App\Filament\Operations\Resources\TelemedicineListLaboratories\TelemedicineListLaboratoryResource;
use App\Support\Filament\Operations\TelemedicineClinicalCatalogForm;
use App\Support\Filament\Operations\TelemedicineClinicalCatalogPages;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateTelemedicineListLaboratory extends CreateRecord
{
    protected static string $resource = TelemedicineListLaboratoryResource::class;

    protected static ?string $title = 'Crear laboratorio';

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
            'Guardar laboratorio',
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
        return TelemedicineClinicalCatalogPages::createdNotification('laboratorio', $this->getRecord()?->name);
    }
}
