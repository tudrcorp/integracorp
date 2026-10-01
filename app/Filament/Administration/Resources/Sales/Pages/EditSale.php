<?php

namespace App\Filament\Administration\Resources\Sales\Pages;

use App\Filament\Administration\Resources\Sales\SaleResource;
use App\Models\Sale;
use App\Support\Sales\SaleDeletion;
use App\Support\SecurityAudit;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Throwable;

class EditSale extends EditRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalHeading('ELIMINAR VENTA')
                ->modalDescription('Se eliminará la venta junto con su recibo de pago, comisión, cuotas de cobranza, cobranza anual, movimientos de crédito de empresa aliada y PDFs generados. La afiliación y sus afiliados no se modifican. Esta acción no se puede deshacer.')
                ->modalSubmitActionLabel('Sí, eliminar')
                ->before(function (DeleteAction $action, Sale $record): void {
                    $reason = SaleDeletion::blockingReason($record);

                    if ($reason === null) {
                        return;
                    }

                    SecurityAudit::log('AUDIT_ADMIN_SALES_DELETE_BLOCKED', 'administration.sales.delete', [
                        'panel' => 'administration',
                        'record_id' => $record->getKey(),
                        'invoice_number' => $record->invoice_number,
                        'reason' => $reason,
                    ], Auth::user());

                    Notification::make()
                        ->title('No se eliminó la venta')
                        ->body($reason)
                        ->warning()
                        ->persistent()
                        ->send();

                    $action->cancel();
                })
                ->using(function (Sale $record): bool {
                    try {
                        $report = SaleDeletion::delete([$record]);
                    } catch (Throwable $th) {
                        SecurityAudit::log('AUDIT_ADMIN_SALES_DELETE_FAILED', 'administration.sales.delete', [
                            'panel' => 'administration',
                            'record_id' => $record->getKey(),
                            'invoice_number' => $record->invoice_number,
                            'error_message' => $th->getMessage(),
                            'error_class' => $th::class,
                        ], Auth::user());

                        Notification::make()
                            ->title('No se eliminó la venta')
                            ->body($th instanceof InvalidArgumentException
                                ? $th->getMessage()
                                : 'Ocurrió un error y no se borró nada. Intente de nuevo o contacte a soporte.')
                            ->danger()
                            ->send();

                        return false;
                    }

                    SecurityAudit::log('AUDIT_ADMIN_SALES_DELETED', 'administration.sales.delete', [
                        'panel' => 'administration',
                        'record_id' => $record->getKey(),
                        'invoice_number' => $record->invoice_number,
                        'deleted' => $report,
                    ], Auth::user());

                    return $report['sales'] === 1;
                })
                ->successNotificationTitle('¡ELIMINADO CON EXITO!')
                ->failureNotification(null),
        ];
    }
}
