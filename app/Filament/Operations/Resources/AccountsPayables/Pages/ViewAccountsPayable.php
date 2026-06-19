<?php

namespace App\Filament\Operations\Resources\AccountsPayables\Pages;

use App\Filament\Operations\Resources\AccountsPayables\AccountsPayableResource;
use App\Models\OperationQuoteGenerator;
use App\Support\Filament\FilamentIosButton;
use App\Support\Operations\AccountsPayablePresenter;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;

class ViewAccountsPayable extends ViewRecord
{
    protected static string $resource = AccountsPayableResource::class;

    protected static ?string $title = 'Detalle de cuenta por pagar';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record = $this->getRecord()->load([
            'telemedicinePatient:id,full_name',
            'telemedicineCase:id,code,patient_name',
            'supplier:id,name,razon_social',
            'operationServiceOrder:id,order_number,supplier_id,supplier_external,associated_quote_pdf_path,service_order_pdf_path',
            'operationServiceOrder.supplier:id,name,razon_social',
            'operationCoordinationService:id,patient,telemedicine_case_id',
            'operationCoordinationService.telemedicineCase:id,code',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview_quote_pdf')
                ->label('Ver PDF Cotización')
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('warning')
                ->button()
                ->extraAttributes([
                    'x-on:click.stop' => '',
                    'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
                ])
                ->modalHeading('Vista previa de cotización')
                ->modalDescription('Revise el PDF de la cotización antes de continuar con el proceso de pago.')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalIcon(Heroicon::OutlinedEye)
                ->modalIconColor('warning')
                ->modalContent(function (): ViewContract {
                    /** @var OperationQuoteGenerator $record */
                    $record = $this->getRecord();
                    $previewUrl = AccountsPayablePresenter::quotePdfPreviewUrl($record);

                    return View::make('filament.operations.accounts-payables.pdf-preview', [
                        'pdfPreviewUrl' => $previewUrl,
                        'pdfDownloadUrl' => $previewUrl,
                        'documentLabel' => 'Cotización',
                        'documentTitle' => AccountsPayablePresenter::quoteNumber($record),
                    ]);
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->modalCancelAction(
                    fn (Action $action): Action => $action
                        ->extraAttributes([
                            'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                        ])
                )
                ->disabled(fn (): bool => ! AccountsPayablePresenter::hasQuotePdf($this->getRecord()))
                ->tooltip(fn (): ?string => AccountsPayablePresenter::hasQuotePdf($this->getRecord())
                    ? 'Abrir vista previa del PDF de la cotización'
                    : 'Esta cotización aún no tiene PDF generado')
                ->action(fn () => null),

            Action::make('preview_service_order_pdf')
                ->label('Ver PDF Orden de Servicio')
                ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                ->color('info')
                ->button()
                ->extraAttributes([
                    'x-on:click.stop' => '',
                    'class' => FilamentIosButton::extraClassForFilamentColor('info'),
                ])
                ->modalHeading('Vista previa de orden de servicio')
                ->modalDescription('Visualice el documento corporativo de la orden vinculada a esta cuenta por pagar.')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalIcon(Heroicon::OutlinedEye)
                ->modalIconColor('info')
                ->modalContent(function (): ViewContract {
                    /** @var OperationQuoteGenerator $record */
                    $record = $this->getRecord();

                    return View::make('filament.operations.accounts-payables.pdf-preview', [
                        'pdfPreviewUrl' => AccountsPayablePresenter::serviceOrderPdfPreviewUrl($record),
                        'pdfDownloadUrl' => AccountsPayablePresenter::serviceOrderPdfDownloadUrl($record),
                        'documentLabel' => 'Orden de servicio',
                        'documentTitle' => AccountsPayablePresenter::serviceOrderNumber($record) ?? 'Sin número asignado',
                    ]);
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->modalCancelAction(
                    fn (Action $action): Action => $action
                        ->extraAttributes([
                            'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                        ])
                )
                ->disabled(fn (): bool => ! AccountsPayablePresenter::hasServiceOrderPdf($this->getRecord()))
                ->tooltip(fn (): ?string => AccountsPayablePresenter::hasServiceOrderPdf($this->getRecord())
                    ? 'Abrir vista previa del PDF de la orden de servicio'
                    : 'Aún no hay orden de servicio vinculada')
                ->action(fn () => null),

            Action::make('back')
                ->label('Volver')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->button()
                ->extraAttributes([
                    'x-on:click.stop' => '',
                    'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                ])
                ->url(AccountsPayableResource::getUrl()),
        ];
    }
}
