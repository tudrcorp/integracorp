<?php

namespace App\Filament\Operations\Resources\OperationMedicationInvoices\Pages;

use App\Filament\Operations\Resources\OperationMedicationInvoices\Actions\MedicationInvoiceActions;
use App\Filament\Operations\Resources\OperationMedicationInvoices\OperationMedicationInvoiceResource;
use App\Models\OperationMedicationInvoice;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class ViewOperationMedicationInvoice extends ViewRecord
{
    protected static string $resource = OperationMedicationInvoiceResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var OperationMedicationInvoice $invoice */
        $invoice = $this->getRecord();

        return 'Factura '.$invoice->invoice_number;
    }

    public function getSubheading(): ?string
    {
        /** @var OperationMedicationInvoice $invoice */
        $invoice = $this->getRecord();

        return $invoice->patient_name.($invoice->case_code ? ' · Caso '.$invoice->case_code : '');
    }

    protected function resolveRecord(int|string $key): Model
    {
        /** @var OperationMedicationInvoice $record */
        $record = parent::resolveRecord($key);

        return $record->load(['items', 'events', 'accountsReceivable:id,operation_medication_invoice_id,status', 'telemedicinePatient']);
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ActionGroup::make([
                    MedicationInvoiceActions::previewPdf(),
                    MedicationInvoiceActions::emailPdf(),
                    MedicationInvoiceActions::whatsAppPdf(),
                ])->dropdown(false),
                ActionGroup::make([
                    MedicationInvoiceActions::registerPayment()
                        ->after(fn () => $this->refreshInvoice()),
                    MedicationInvoiceActions::annul()
                        ->after(fn () => $this->refreshInvoice()),
                ])->dropdown(false),
            ])
                ->label('Acciones')
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->color('primary')
                ->button()
                ->extraAttributes(['class' => FilamentIosButton::extraClassForFilamentColor('primary')]),
            Action::make('back')
                ->label('Volver')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->extraAttributes(['class' => FilamentIosButton::extraClassForFilamentColor('gray')])
                ->url(OperationMedicationInvoiceResource::getUrl()),
        ];
    }

    private function refreshInvoice(): void
    {
        $this->record = $this->getRecord()->refresh()->load(['items', 'events', 'accountsReceivable:id,operation_medication_invoice_id,status']);
    }
}
