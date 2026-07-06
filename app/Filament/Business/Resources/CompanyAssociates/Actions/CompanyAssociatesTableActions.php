<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CompanyAssociates\Actions;

use App\Models\CompanyAssociate;
use App\Support\Companies\CompanyAssociateCarnetGenerator;
use App\Support\Companies\CompanyAssociateInclusionQrCatalog;
use App\Support\Companies\CompanyAssociateVoucherIlsUpdater;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;
use RuntimeException;

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
            ->action(function (CompanyAssociate $record, array $data): void {
                CompanyAssociateVoucherIlsUpdater::save($record, $data);
            })
            ->successNotification(fn (CompanyAssociate $record): Notification => Notification::make()
                ->success()
                ->title('Voucher ILS guardado')
                ->body('El voucher de '.$record->full_name.' se registró correctamente.'));
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
            ->action(function (CompanyAssociate $record): void {
                try {
                    $result = CompanyAssociateCarnetGenerator::generate($record);
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
            ->visible(fn (CompanyAssociate $record): bool => CompanyAssociateInclusionQrCatalog::qrExists())
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
            ->visible(fn (CompanyAssociate $record): bool => CompanyAssociateCarnetGenerator::absolutePathFor($record) !== null);
    }
}
