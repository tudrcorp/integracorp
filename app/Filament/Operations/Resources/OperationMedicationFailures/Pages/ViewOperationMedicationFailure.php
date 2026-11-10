<?php

namespace App\Filament\Operations\Resources\OperationMedicationFailures\Pages;

use App\Filament\Operations\Resources\OperationMedicationFailures\Actions\MedicationFailureActions;
use App\Filament\Operations\Resources\OperationMedicationFailures\OperationMedicationFailureResource;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewOperationMedicationFailure extends ViewRecord
{
    protected static string $resource = OperationMedicationFailureResource::class;

    protected static ?string $title = 'Falla de medicamento';

    protected function getHeaderActions(): array
    {
        return [
            MedicationFailureActions::markAcquired()->after(fn () => $this->getRecord()->refresh()),
            MedicationFailureActions::discard()->after(fn () => $this->getRecord()->refresh()),
            Action::make('back')
                ->label('Volver')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->extraAttributes(['class' => FilamentIosButton::extraClassForFilamentColor('gray')])
                ->url(OperationMedicationFailureResource::getUrl()),
        ];
    }
}
