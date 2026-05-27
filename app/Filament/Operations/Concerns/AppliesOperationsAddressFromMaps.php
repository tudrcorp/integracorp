<?php

declare(strict_types=1);

namespace App\Filament\Operations\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

trait AppliesOperationsAddressFromMaps
{
    public function applySupplierLocationFromMaps(string $address): void
    {
        $this->persistAddressFromMaps(
            address: $address,
            successTitle: 'Dirección actualizada',
            successBody: 'La ubicación principal del proveedor se guardó correctamente.',
            persist: function (Model $record, string $normalizedAddress): void {
                $record->ubicacion_principal = $normalizedAddress;
                $record->updated_by = \Illuminate\Support\Facades\Auth::user()?->name;
                $record->save();
            },
        );
    }

    public function applyAffiliateLocationFromMaps(string $address): void
    {
        $this->persistAddressFromMaps(
            address: $address,
            successTitle: 'Dirección actualizada',
            successBody: 'La dirección del afiliado se guardó correctamente.',
            persist: function (Model $record, string $normalizedAddress): void {
                $record->address = $normalizedAddress;
                $record->save();
            },
        );
    }

    public function applyAffiliateCorporateLocationFromMaps(string $address): void
    {
        $this->persistAddressFromMaps(
            address: $address,
            successTitle: 'Dirección actualizada',
            successBody: 'La dirección del afiliado corporativo se guardó correctamente.',
            persist: function (Model $record, string $normalizedAddress): void {
                $record->address = $normalizedAddress;
                $record->save();
            },
        );
    }

    public function applyAffiliationCorporateLocationFromMaps(string $address): void
    {
        $address = mb_strtoupper(trim($address));

        if ($address === '') {
            Notification::make()
                ->title('Dirección vacía')
                ->body('Seleccione o busque una ubicación en el mapa antes de guardar.')
                ->warning()
                ->send();

            return;
        }

        $affiliateCorporate = $this->getRecord();
        $corporate = $affiliateCorporate->affiliationCorporate;

        if ($corporate === null) {
            Notification::make()
                ->title('Sin corporativo vinculado')
                ->body('Este afiliado no tiene empresa corporativa asociada.')
                ->warning()
                ->send();

            return;
        }

        $corporate->address = $address;
        $corporate->save();

        $this->record = $affiliateCorporate->fresh(['affiliationCorporate']);

        Notification::make()
            ->title('Dirección actualizada')
            ->body('La dirección del corporativo contratante se guardó correctamente.')
            ->success()
            ->send();

        $this->unmountAction();
    }

    /**
     * @param  callable(Model, string): void  $persist
     */
    private function persistAddressFromMaps(string $address, string $successTitle, string $successBody, callable $persist): void
    {
        $address = mb_strtoupper(trim($address));

        if ($address === '') {
            Notification::make()
                ->title('Dirección vacía')
                ->body('Seleccione o busque una ubicación en el mapa antes de guardar.')
                ->warning()
                ->send();

            return;
        }

        /** @var Model $record */
        $record = $this->getRecord();
        $persist($record, $address);

        $this->record = $record->fresh();

        Notification::make()
            ->title($successTitle)
            ->body($successBody)
            ->success()
            ->send();

        $this->unmountAction();
    }
}
