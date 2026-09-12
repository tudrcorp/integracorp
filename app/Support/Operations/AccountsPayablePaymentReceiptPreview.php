<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationAccountsPayable;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;

/**
 * Vista previa del comprobante de pago asociado a una cuenta por pagar.
 */
final class AccountsPayablePaymentReceiptPreview
{
    public static function url(OperationAccountsPayable $record): ?string
    {
        if (! $record->hasPaymentReceipt()) {
            return null;
        }

        return URL::to(Storage::url((string) $record->payment_receipt_path));
    }

    public static function isPdf(OperationAccountsPayable $record): bool
    {
        return str_ends_with(mb_strtolower((string) $record->payment_receipt_path), '.pdf');
    }

    public static function action(): Action
    {
        return Action::make('previewPaymentReceipt')
            ->label('Ver comprobante')
            ->icon('heroicon-m-banknotes')
            ->color('success')
            ->modalHeading(fn (OperationAccountsPayable $record): string => 'Comprobante de pago · factura '.$record->invoice_number)
            ->modalDescription(fn (OperationAccountsPayable $record): string => trim(
                $record->supplier_name
                .' · '.($record->payment_reference ?: 'Sin referencia')
                .' · '.($record->payment_date?->format('d/m/Y') ?: 'Sin fecha')
            ))
            ->modalIcon('heroicon-m-banknotes')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->extraModalFooterActions(fn (OperationAccountsPayable $record): array => [
                Action::make('openPaymentReceiptTab')
                    ->label('Abrir en pestaña nueva')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(self::url($record), shouldOpenInNewTab: true),
                Action::make('downloadPaymentReceipt')
                    ->label('Descargar')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('gray')
                    ->url(self::url($record), shouldOpenInNewTab: true),
            ])
            ->form(fn (OperationAccountsPayable $record): array => [
                Placeholder::make('payment_receipt_preview')
                    ->hiddenLabel()
                    ->content(fn (): HtmlString => self::render($record))
                    ->columnSpanFull(),
            ])
            ->visible(fn (OperationAccountsPayable $record): bool => $record->hasPaymentReceipt());
    }

    public static function render(OperationAccountsPayable $record): HtmlString
    {
        $url = self::url($record);

        $frame = 'border-radius:14px;overflow:hidden;border:1px solid rgba(120,130,150,0.28);'
            .'background:rgba(120,130,150,0.08);box-shadow:inset 0 1px 2px rgba(0,0,0,0.06);';

        if ($url === null) {
            return new HtmlString(
                '<div style="'.$frame.'padding:2.5rem 1.25rem;text-align:center;font-size:0.875rem;opacity:0.7;">'
                .'Esta cuenta por pagar no tiene comprobante de pago adjunto.'
                .'</div>'
            );
        }

        $safeUrl = e($url);
        $title = e((string) ($record->payment_reference ?: $record->invoice_number));

        if (self::isPdf($record)) {
            return new HtmlString(
                '<div style="'.$frame.'">'
                .'<iframe src="'.$safeUrl.'#view=FitH" title="Comprobante '.$title.'" loading="lazy" '
                .'style="display:block;width:100%;height:78vh;min-height:560px;border:0;"></iframe>'
                .'</div>'
            );
        }

        return new HtmlString(
            '<div style="'.$frame.'display:flex;align-items:center;justify-content:center;padding:0.75rem;'
            .'max-height:78vh;min-height:420px;overflow:auto;">'
            .'<img src="'.$safeUrl.'" alt="Comprobante '.$title.'" loading="lazy" '
            .'style="display:block;max-width:100%;max-height:76vh;object-fit:contain;border-radius:10px;">'
            .'</div>'
        );
    }
}
