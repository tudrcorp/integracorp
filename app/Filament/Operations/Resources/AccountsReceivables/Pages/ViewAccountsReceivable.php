<?php

namespace App\Filament\Operations\Resources\AccountsReceivables\Pages;

use App\Filament\Operations\Resources\AccountsReceivables\AccountsReceivableResource;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewAccountsReceivable extends ViewRecord
{
    protected static string $resource = AccountsReceivableResource::class;

    protected static ?string $title = 'Detalle de cuenta por cobrar';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record = $this->getRecord()->load([
            'telemedicinePatient:id,full_name',
            'telemedicineCase:id,code,patient_name',
            'reassignmentSupplier:id,name,razon_social',
            'reassignedByUser:id,name',
            'operationCoordinationService:id,patient,telemedicine_case_id',
            'operationCoordinationService.telemedicineCase:id,code',
            'operationQuoteGenerator',
            'operationServiceOrder',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->button()
                ->extraAttributes([
                    'x-on:click.stop' => '',
                    'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                ])
                ->url(AccountsReceivableResource::getUrl()),
        ];
    }
}
