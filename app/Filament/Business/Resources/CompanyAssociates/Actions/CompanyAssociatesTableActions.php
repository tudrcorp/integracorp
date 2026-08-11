<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CompanyAssociates\Actions;

use App\Filament\Business\Resources\CompanyAssociates\Pages\ListCompanyAssociates;
use App\Http\Controllers\CompanyAssociateExportCsvController;
use App\Models\CompanyAssociate;
use App\Support\Companies\CompanyAssociateCarnetGenerator;
use App\Support\Companies\CompanyAssociateInclusionQrCatalog;
use App\Support\Companies\CompanyAssociateStatusManager;
use App\Support\Companies\CompanyAssociateVoucherIlsUpdater;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class CompanyAssociatesTableActions
{
    public static function uploadVoucherIlsAction(): Action
    {
        return Action::make('uploadVoucherIls')
            ->label('Voucher ILS')
            ->icon(Heroicon::Ticket)
            ->color('info')
            ->modalIcon(Heroicon::OutlinedTicket)
            ->modalHeading(fn (CompanyAssociate $record): string => 'Voucher ILS — '.$record->full_name)
            ->modalDescription('Cargue o actualice el código, vigencia e imagen del voucher ILS del asociado.')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Guardar voucher')
            ->fillForm(fn (CompanyAssociate $record): array => CompanyAssociateVoucherIlsUpdater::formDefaults($record))
            ->form(CompanyAssociateVoucherIlsUpdater::formComponents(
                fn (CompanyAssociate $record): bool => blank($record->document_ils),
            ))
            ->visible(fn (CompanyAssociate $record): bool => ! $record->isAnnulled())
            ->action(function (CompanyAssociate $record, array $data): void {
                CompanyAssociateVoucherIlsUpdater::save($record, $data);
            })
            ->successNotification(fn (CompanyAssociate $record): Notification => Notification::make()
                ->success()
                ->title('Voucher ILS guardado')
                ->body('El voucher de '.$record->full_name.' se registró correctamente. El estatus pasó a ACTIVO.'));
    }

    public static function annulAssociateAction(): Action
    {
        return Action::make('annulAssociate')
            ->label('Anular asociado')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedNoSymbol)
            ->modalIconColor('danger')
            ->modalHeading(fn (CompanyAssociate $record): string => 'Anular asociado — '.$record->full_name)
            ->modalDescription('El estatus pasará de ACTIVO a ANULADO. El día consumido se devolverá al responsable. Debe indicar la razón de la anulación.')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Anular asociado')
            ->modalCancelActionLabel('Cancelar')
            ->closeModalByClickingAway(false)
            ->visible(fn (CompanyAssociate $record): bool => $record->canBeAnnulled())
            ->form([
                Textarea::make('annulment_reason')
                    ->label('Razón de la anulación')
                    ->placeholder('Explique de forma clara por qué se anula este asociado…')
                    ->helperText('Campo obligatorio. Mínimo '.CompanyAssociateStatusManager::ANNULMENT_REASON_MIN_LENGTH.' caracteres. Quedará registrado en la ficha y en las trazas de seguridad.')
                    ->required()
                    ->minLength(CompanyAssociateStatusManager::ANNULMENT_REASON_MIN_LENGTH)
                    ->maxLength(5000)
                    ->rows(4)
                    ->columnSpanFull()
                    ->validationMessages([
                        'required' => 'Debe indicar la razón de la anulación.',
                        'minLength' => 'La razón debe tener al menos '.CompanyAssociateStatusManager::ANNULMENT_REASON_MIN_LENGTH.' caracteres.',
                    ]),
            ])
            ->action(function (CompanyAssociate $record, array $data): void {
                try {
                    CompanyAssociateStatusManager::annul(
                        $record,
                        (string) ($data['annulment_reason'] ?? ''),
                    );

                    Notification::make()
                        ->success()
                        ->title('Asociado anulado')
                        ->body('Se anuló a '.$record->full_name.' y se devolvió 1 día al responsable.')
                        ->send();
                } catch (ValidationException $exception) {
                    throw $exception;
                } catch (Throwable $throwable) {
                    report($throwable);

                    Notification::make()
                        ->danger()
                        ->title('No se pudo anular el asociado')
                        ->body($throwable->getMessage())
                        ->send();
                }
            });
    }

    public static function generateCarnetAction(): Action
    {
        return Action::make('generateCarnet')
            ->label('Generar carnet')
            ->icon(Heroicon::OutlinedIdentification)
            ->color('success')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedIdentification)
            ->modalHeading(fn (CompanyAssociate $record): string => 'Generar carnet — '.$record->full_name)
            ->modalDescription('Se generará la tarjeta PDF del asociado con sus datos personales y la vigencia según la fecha de vuelo o el voucher ILS registrado.')
            ->modalSubmitActionLabel('Generar carnet')
            ->visible(fn (CompanyAssociate $record): bool => ! $record->isAnnulled())
            ->action(function (CompanyAssociate $record): void {
                try {
                    CompanyAssociateCarnetGenerator::generate($record);
                } catch (RuntimeException $exception) {
                    Notification::make()
                        ->danger()
                        ->title('No se pudo generar el carnet')
                        ->body($exception->getMessage())
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Carnet generado')
                    ->body('La tarjeta de '.$record->full_name.' está lista. Use «Abrir carnet» en el menú de acciones.')
                    ->send();
            });
    }

    public static function previewInclusionQrAction(): Action
    {
        return Action::make('previewInclusionQr')
            ->label('Vista previa QR')
            ->icon(Heroicon::OutlinedQrCode)
            ->color('warning')
            ->modalHeading(fn (CompanyAssociate $record): string => 'Vista previa del QR — '.$record->full_name)
            ->modalDescription('Escanee el código con su teléfono para validar que abre el PDF de canales de comunicación del plan INCLUSIÓN.')
            ->modalIcon(Heroicon::OutlinedQrCode)
            ->modalIconColor('warning')
            ->modalWidth(Width::Large)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->modalContent(fn (CompanyAssociate $record): ViewContract => View::make('filament.business.company-associates.inclusion-qr-preview', [
                'associate' => $record,
                'planLabel' => CompanyAssociateInclusionQrCatalog::PLAN_LABEL,
                'qrPreviewUrl' => CompanyAssociateInclusionQrCatalog::qrPreviewUrl(),
                'pdfDestinationUrl' => CompanyAssociateInclusionQrCatalog::pdfPublicUrl(),
            ]))
            ->visible(fn (CompanyAssociate $record): bool => ! $record->isAnnulled() && CompanyAssociateInclusionQrCatalog::qrExists())
            ->action(fn (): null => null);
    }

    public static function openCarnetAction(): Action
    {
        return Action::make('openCarnet')
            ->label('Abrir carnet')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->url(fn (CompanyAssociate $record): ?string => CompanyAssociateCarnetGenerator::publicUrlFor($record))
            ->openUrlInNewTab()
            ->visible(fn (CompanyAssociate $record): bool => ! $record->isAnnulled()
                && CompanyAssociateCarnetGenerator::absolutePathFor($record) !== null);
    }

    public static function sendDocumentsBulkAction(): BulkAction
    {
        return BulkAction::make('sendDocuments')
            ->label('Enviar carnet y QR')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('primary')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedPaperAirplane)
            ->modalHeading('Enviar carnet y QR')
            ->modalDescription('Se generará la tarjeta del asociado y se enviará por correo y WhatsApp. El responsable recibirá copia cuando tenga datos de contacto registrados.')
            ->modalSubmitActionLabel('Enviar documentos')
            ->modalCancelActionLabel('Cancelar')
            ->modalSubmitAction(function (Action $action): Action {
                return $action
                    ->action(null)
                    ->extraAttributes(['data-associate-documents-submit' => '1'])
                    ->alpineClickHandler("\$dispatch('company-associate-documents-send-start')");
            })
            ->before(function (ListCompanyAssociates $livewire): void {
                $livewire->resetAssociateDocumentsBulkSendProgress();
            })
            ->modalContent(fn (Collection $records): ViewContract => View::make('filament.business.company-associates.send-documents-bulk-modal', [
                'associates' => $records
                    ->filter(fn ($record): bool => $record instanceof CompanyAssociate)
                    ->map(fn (CompanyAssociate $record): array => [
                        'id' => (int) $record->getKey(),
                        'name' => (string) $record->full_name,
                    ])
                    ->values()
                    ->all(),
            ]))
            ->action(fn (): null => null);
    }

    public static function exportCsvBulkAction(): BulkAction
    {
        return BulkAction::make('exportCsv')
            ->label('Exportar CSV')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('success')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records) {
                if ($records->isEmpty()) {
                    Notification::make()
                        ->warning()
                        ->title('Selecciona al menos un asociado')
                        ->body('Marca los registros que deseas exportar o usa «Seleccionar todos» en la tabla.')
                        ->send();

                    return;
                }

                $ids = $records
                    ->filter(fn ($record): bool => $record instanceof CompanyAssociate)
                    ->pluck('id')
                    ->all();

                $token = CompanyAssociateExportCsvController::storeIdsAndGetToken($ids);

                return redirect()->route('business.company-associates.export-csv', ['token' => $token]);
            });
    }
}
