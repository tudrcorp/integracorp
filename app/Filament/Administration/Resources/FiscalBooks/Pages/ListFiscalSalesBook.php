<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\FiscalBooks\Pages;

use App\Filament\Administration\Resources\FiscalBooks\FiscalSalesBookResource;
use App\Support\Administration\FiscalBooks\FiscalSalesBookSync;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ListFiscalSalesBook extends ListFiscalBookEntries
{
    protected static string $resource = FiscalSalesBookResource::class;

    protected static function bookResource(): string
    {
        return FiscalSalesBookResource::class;
    }

    protected function sourceActions(): array
    {
        return [
            Action::make('syncFromSales')
                ->label('Traer facturas de Ventas')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (): bool => ! $this->isClosed())
                ->requiresConfirmation()
                ->modalIcon(Heroicon::OutlinedArrowPath)
                ->modalHeading('Traer facturas de Ventas')
                ->modalDescription('Revisa las facturas fiscales emitidas en Administración → Ventas con fecha en este mes y agrega las que falten. No duplica las que ya están ni cambia las que se ajustaron a mano. Las facturas nuevas llegan solas al emitirse; use esto para completar el mes.')
                ->modalSubmitActionLabel('Traer facturas')
                ->action(function (): void {
                    try {
                        $report = FiscalSalesBookSync::syncPeriod($this->periodDate(), Auth::user());
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()->title('No se trajeron facturas')->body($exception->getMessage())->danger()->send();

                        return;
                    }

                    $lines = [
                        $report['creadas'].' nuevas, '.$report['actualizadas'].' actualizadas, '.$report['existentes'].' ya estaban en el libro.',
                    ];

                    if ($report['sin_monto'] !== []) {
                        $lines[] = 'Sin monto en bolívares (regenere la factura en Ventas): '.implode(', ', array_slice($report['sin_monto'], 0, 10)).(count($report['sin_monto']) > 10 ? '…' : '').'.';
                    }

                    if ($report['errores'] !== []) {
                        $lines[] = implode(' ', array_slice($report['errores'], 0, 5));
                    }

                    Notification::make()
                        ->title('Facturas de Ventas revisadas')
                        ->body(implode(' ', $lines))
                        ->color($report['sin_monto'] === [] && $report['errores'] === [] ? 'success' : 'warning')
                        ->icon($report['sin_monto'] === [] && $report['errores'] === [] ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedExclamationTriangle)
                        ->persistent($report['sin_monto'] !== [] || $report['errores'] !== [])
                        ->send();
                }),
        ];
    }
}
