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
 * Vista previa del documento de la factura asociado a una cuenta por pagar.
 */
final class AccountsPayableInvoicePreview
{
    public static function url(OperationAccountsPayable $record): ?string
    {
        if (! filled($record->invoice_file_path)) {
            return null;
        }

        return URL::to(Storage::url((string) $record->invoice_file_path));
    }

    public static function isPdf(OperationAccountsPayable $record): bool
    {
        return str_ends_with(mb_strtolower((string) $record->invoice_file_path), '.pdf');
    }

    /**
     * Acción de vista previa lista para usar en tabla y en ficha.
     */
    public static function action(): Action
    {
        return Action::make('previewInvoice')
            ->label('Ver factura')
            ->icon('heroicon-m-document-magnifying-glass')
            ->color('info')
            ->modalHeading(fn (OperationAccountsPayable $record): string => 'Factura '.$record->invoice_number)
            ->modalDescription(fn (OperationAccountsPayable $record): string => $record->supplier_name.' · RIF '.$record->supplier_rif)
            ->modalIcon('heroicon-m-document-magnifying-glass')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->extraModalFooterActions(fn (OperationAccountsPayable $record): array => [
                Action::make('openInvoiceTab')
                    ->label('Abrir en pestaña nueva')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(self::url($record), shouldOpenInNewTab: true),
                Action::make('downloadInvoice')
                    ->label('Descargar')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('gray')
                    ->url(self::url($record), shouldOpenInNewTab: true),
            ])
            ->form(fn (OperationAccountsPayable $record): array => [
                Placeholder::make('invoice_preview')
                    ->hiddenLabel()
                    ->content(fn (): HtmlString => self::render($record))
                    ->columnSpanFull(),
            ])
            ->visible(fn (OperationAccountsPayable $record): bool => $record->hasInvoiceDocument());
    }

    /**
     * El panel de Operaciones no compila un tema Filament propio, así que las
     * clases utilitarias arbitrarias de Tailwind no existen en el CSS servido.
     * Por eso el tamaño del visor va en estilos en línea: es lo único que
     * garantiza que el documento se vea grande en cualquier panel.
     */
    public static function render(OperationAccountsPayable $record): HtmlString
    {
        $url = self::url($record);

        $frame = 'border-radius:14px;overflow:hidden;border:1px solid rgba(120,130,150,0.28);'
            .'background:rgba(120,130,150,0.08);box-shadow:inset 0 1px 2px rgba(0,0,0,0.06);';

        if ($url === null) {
            return new HtmlString(
                '<div style="'.$frame.'padding:2.5rem 1.25rem;text-align:center;font-size:0.875rem;opacity:0.7;">'
                .'Esta cuenta por pagar no tiene documento de factura adjunto.'
                .'</div>'
            );
        }

        $safeUrl = e($url);
        $title = e($record->invoice_number);

        if (self::isPdf($record)) {
            return new HtmlString(
                '<div style="'.$frame.'">'
                .'<iframe src="'.$safeUrl.'#view=FitH" title="Factura '.$title.'" loading="lazy" '
                .'style="display:block;width:100%;height:78vh;min-height:560px;border:0;"></iframe>'
                .'</div>'
            );
        }

        return new HtmlString(
            '<div style="'.$frame.'display:flex;align-items:center;justify-content:center;padding:0.75rem;'
            .'max-height:78vh;min-height:420px;overflow:auto;">'
            .'<img src="'.$safeUrl.'" alt="Factura '.$title.'" loading="lazy" '
            .'style="display:block;max-width:100%;max-height:76vh;object-fit:contain;border-radius:10px;">'
            .'</div>'
        );
    }
}
