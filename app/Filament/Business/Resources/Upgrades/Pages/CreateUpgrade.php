<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Upgrades\Pages;

use App\Filament\Business\Resources\Upgrades\UpgradeResource;
use App\Support\Upgrades\UpgradeCatalogPersistence;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class CreateUpgrade extends CreateRecord
{
    protected static string $resource = UpgradeResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return UpgradeCatalogPersistence::save(null, $data);
        } catch (InvalidArgumentException $exception) {
            Notification::make()
                ->danger()
                ->title('Revise el upgrade')
                ->body($exception->getMessage())
                ->persistent()
                ->send();

            throw (new Halt)->rollBackDatabaseTransaction();
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Upgrade creado';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
