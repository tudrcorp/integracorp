<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationMedicationInvoices\Actions;

use App\Models\OperationMedicationInvoice;
use App\Support\Operations\MedicationInvoicePayerChange;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Cambiar a nombre de quién van varias facturas a la vez: el paciente o el grupo corporativo.
 * Cada factura toma los datos de su propio paciente o grupo; las que no se pueden cambiar
 * (anuladas, afiliados individuales sin grupo, fichas sin dirección…) se omiten con el motivo.
 */
final class ChangeMedicationInvoicesPayerBulkAction
{
    public const MAX_SELECTED = 300;

    /** Cuántas omisiones se detallan en el aviso; el resto se resume. */
    private const SKIPPED_SHOWN = 8;

    public static function make(): BulkAction
    {
        return BulkAction::make('change_medication_invoices_payer')
            ->label('Cambiar a nombre de quién van')
            ->icon(Heroicon::OutlinedUserCircle)
            ->color('primary')
            ->visible(fn (): bool => MedicationInvoiceActions::canChangePayer())
            ->fetchSelectedRecords(false)
            ->modalHeading('A nombre de quién van las facturas seleccionadas')
            ->modalDescription('Cada factura toma los datos de la ficha vigente de su propio paciente o de su grupo corporativo. Las anuladas, las de afiliados sin grupo y las que no tengan dirección en la ficha se omiten y se le indica por qué.')
            ->modalIcon(Heroicon::OutlinedUserCircle)
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Aplicar')
            ->schema([
                Radio::make('payer_type')
                    ->label('Las facturas van a nombre de')
                    ->options(OperationMedicationInvoice::payerOptions())
                    ->descriptions([
                        OperationMedicationInvoice::PAYER_PATIENT => 'Nombre, cédula y dirección de cada paciente.',
                        OperationMedicationInvoice::PAYER_CORPORATE => 'Razón social, RIF y dirección del grupo corporativo (o empresa de Nuevos Negocios) de cada paciente.',
                    ])
                    ->required(),
                Textarea::make('reason')
                    ->label('Motivo del cambio')
                    ->placeholder('Ej.: la empresa asume el pago de estos servicios…')
                    ->rows(2)
                    ->minLength(MedicationInvoicePayerChange::REASON_MIN)
                    ->maxLength(MedicationInvoicePayerChange::REASON_MAX)
                    ->required(),
            ])
            ->deselectRecordsAfterCompletion()
            ->action(function (BulkAction $action, array $data): void {
                if (Auth::user() === null || ! MedicationInvoiceActions::canChangePayer()) {
                    Notification::make()->title('Sin permiso')->body('No tiene permiso para cambiar a nombre de quién van las facturas a pacientes.')->danger()->send();

                    return;
                }

                $ids = $action->getSelectedRecordsQuery()
                    ->limit(self::MAX_SELECTED + 1)
                    ->pluck('operation_medication_invoices.id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->all();

                if ($ids === []) {
                    Notification::make()->title('Sin facturas')->body('Seleccione al menos una factura.')->warning()->send();

                    return;
                }

                if (count($ids) > self::MAX_SELECTED) {
                    Notification::make()->title('Selección demasiado grande')->body('Seleccione hasta '.self::MAX_SELECTED.' facturas por vez.')->warning()->send();

                    return;
                }

                $result = MedicationInvoicePayerChange::changeMany($ids, $data['payer_type'] ?? null, $data['reason'] ?? '');

                self::notify($result, (string) OperationMedicationInvoice::payerLabel($data['payer_type'] ?? null));
            });
    }

    /**
     * @param  array{changed: list<string>, skipped: list<array{number: string, reason: string}>}  $result
     */
    private static function notify(array $result, string $payerLabel): void
    {
        $changed = count($result['changed']);
        $skipped = count($result['skipped']);

        $lines = array_map(
            fn (array $row): string => '• '.$row['number'].': '.$row['reason'],
            array_slice($result['skipped'], 0, self::SKIPPED_SHOWN),
        );

        if ($skipped > self::SKIPPED_SHOWN) {
            $lines[] = '…y '.($skipped - self::SKIPPED_SHOWN).' más.';
        }

        $title = match (true) {
            $changed > 0 && $skipped === 0 => $changed === 1 ? '1 factura actualizada' : $changed.' facturas actualizadas',
            $changed > 0 => $changed.' actualizada(s), '.$skipped.' omitida(s)',
            default => 'No se actualizó ninguna factura',
        };

        $body = ($changed > 0 ? 'Ahora van a nombre de '.mb_strtolower($payerLabel).'.' : '')
            .($lines !== [] ? ($changed > 0 ? "\n\n" : '').'Omitidas:'."\n".implode("\n", $lines) : '');

        $notification = Notification::make()->title($title)->body(nl2br(e($body)));

        match (true) {
            $skipped === 0 => $notification->success(),
            $changed > 0 => $notification->warning()->persistent(),
            default => $notification->danger()->persistent(),
        };

        $notification->send();
    }
}
