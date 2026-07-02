<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CompanyAssociates\Actions;

use App\Models\CompanyAssociate;
use App\Support\Companies\CompanyAssociateCarnetGenerator;
use App\Support\Companies\CompanyAssociateVoucherIlsUpdater;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
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
                ->body('La información del voucher se registró correctamente para '.$record->full_name.'.'));
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
            ->modalDescription('Se generará la tarjeta PDF del asociado con sus datos personales y la vigencia ILS registrada, si existe.')
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

    public static function openCarnetAction(): Action
    {
        return Action::make('openCarnet')
            ->label('Abrir carnet')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->url(fn (CompanyAssociate $record): ?string => ($path = CompanyAssociateCarnetGenerator::absolutePathFor($record))
                ? asset('storage/tarjeta-afiliacion/'.CompanyAssociateCarnetGenerator::filenameFor($record))
                : null)
            ->openUrlInNewTab()
            ->visible(fn (CompanyAssociate $record): bool => CompanyAssociateCarnetGenerator::absolutePathFor($record) !== null);
    }
}
