<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Upgrades\Pages;

use App\Filament\Business\Resources\Upgrades\UpgradeResource;
use App\Models\Upgrade;
use App\Support\Upgrades\UpgradeCatalogPersistence;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class EditUpgrade extends EditRecord
{
    protected static string $resource = UpgradeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('Ver'),
        ];
    }

    public function getSubheading(): ?string
    {
        return 'Cambiar un precio o un rango solo afecta a las asignaciones nuevas y a las renovaciones: quien ya tiene el upgrade conserva su monto.';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Upgrade $record */
        $record = $this->getRecord();

        return UpgradeCatalogPersistence::formState($record);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            /** @var Upgrade $record */
            return UpgradeCatalogPersistence::save($record, $data);
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

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Upgrade actualizado';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
