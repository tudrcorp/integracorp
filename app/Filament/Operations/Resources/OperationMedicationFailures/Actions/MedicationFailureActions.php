<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationMedicationFailures\Actions;

use App\Models\OperationMedicationFailure;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Operations\MedicationFailureRecorder;
use App\Support\Operations\MedicationInventoryCatalog;
use App\Support\Operations\MedicationInvoiceException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Cierre de una falla de medicamento: ADQUIRIDO (opcionalmente ligado al producto del
 * inventario) o DESCARTADO. Exige el permiso también al ejecutarse.
 */
final class MedicationFailureActions
{
    public static function canManage(): bool
    {
        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::MANAGE_MEDICATION_FAILURES);
    }

    public static function markAcquired(): Action
    {
        return Action::make('mark_medication_failure_acquired')
            ->label('Marcar como adquirido')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (OperationMedicationFailure $record): bool => $record->isPendingPurchase() && self::canManage())
            ->modalHeading(fn (OperationMedicationFailure $record): string => 'Adquirido: '.$record->medicine_name)
            ->modalDescription('Indique cómo se resolvió. Si ya está en el inventario, ligue el producto para dejar la traza.')
            ->modalSubmitActionLabel('Marcar como adquirido')
            ->schema([
                Select::make('product_id')
                    ->label('Producto del inventario (opcional)')
                    ->native(false)
                    ->searchable()
                    ->options(fn (OperationMedicationFailure $record): array => MedicationInventoryCatalog::search($record->medicine_name)->pluck('name', 'id')->all())
                    ->getSearchResultsUsing(fn (string $search): array => MedicationInventoryCatalog::search($search)->pluck('name', 'id')->all())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => MedicationInventoryCatalog::productsById([(int) $value])->first()?->name),
                Textarea::make('notes')
                    ->label('Nota')
                    ->placeholder('Ej.: comprado a la farmacia X, factura N.º …; cargado al almacén Diagno-Center.')
                    ->rows(3)
                    ->minLength(5)
                    ->maxLength(2000)
                    ->required(),
            ])
            ->action(fn (OperationMedicationFailure $record, array $data, Action $action) => self::resolve(
                $record,
                OperationMedicationFailure::STATUS_ACQUIRED,
                (string) ($data['notes'] ?? ''),
                filled($data['product_id'] ?? null) ? (int) $data['product_id'] : null,
                $action,
            ));
    }

    public static function discard(): Action
    {
        return Action::make('discard_medication_failure')
            ->label('Descartar')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (OperationMedicationFailure $record): bool => $record->isPendingPurchase() && self::canManage())
            ->requiresConfirmation()
            ->modalHeading(fn (OperationMedicationFailure $record): string => 'Descartar: '.$record->medicine_name)
            ->modalDescription('La falla deja de estar pendiente de compra. Explique por qué no se va a adquirir.')
            ->modalSubmitActionLabel('Descartar')
            ->schema([
                Textarea::make('notes')
                    ->label('Motivo')
                    ->rows(3)
                    ->minLength(5)
                    ->maxLength(2000)
                    ->required(),
            ])
            ->action(fn (OperationMedicationFailure $record, array $data, Action $action) => self::resolve(
                $record,
                OperationMedicationFailure::STATUS_DISCARDED,
                (string) ($data['notes'] ?? ''),
                null,
                $action,
            ));
    }

    private static function resolve(OperationMedicationFailure $record, string $status, string $notes, ?int $productId, Action $action): void
    {
        if (! self::canManage()) {
            Notification::make()->title('Sin permiso')->body('No tiene permiso para gestionar fallas de medicamentos.')->danger()->send();
            $action->halt();
        }

        try {
            $resolved = MedicationFailureRecorder::resolve($record, $status, $notes, $productId);
        } catch (MedicationInvoiceException $exception) {
            Notification::make()->title('No se cerró la falla')->body($exception->getMessage())->danger()->send();
            $action->halt();

            return;
        }

        Notification::make()
            ->title('Falla actualizada')
            ->body('«'.$resolved->medicine_name.'» quedó '.mb_strtolower(OperationMedicationFailure::statusLabel($resolved->status)).'.')
            ->success()
            ->send();
    }
}
