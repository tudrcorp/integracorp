<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\ProspectAgents;

use App\Models\ProspectAgent;
use App\Support\Filament\FilamentIosButton;
use App\Support\ProspectAgents\ProspectAgentDeletion;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Throwable;

/**
 * Acciones de eliminación lógica (con motivo obligatorio) y restauración de prospectos.
 */
final class ProspectAgentDeletionActions
{
    public static function bulkDelete(): BulkAction
    {
        return BulkAction::make('deleteWithReason')
            ->label('Eliminar seleccionados')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalIconColor('danger')
            ->modalHeading('Eliminar prospectos seleccionados')
            ->modalDescription('Los prospectos dejarán de verse en Captación, en sus indicadores y gráficos. No se borran de la base: se pueden consultar y restaurar desde el filtro «Eliminados».')
            ->modalSubmitActionLabel('Eliminar')
            ->modalCancelActionLabel('Cancelar')
            ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes([
                'class' => FilamentIosButton::extraClassForFilamentColor('danger'),
            ]))
            ->schema([self::reasonField()])
            ->action(function (Collection $records, array $data): void {
                self::runDelete($records->filter(fn (ProspectAgent $record): bool => ! $record->trashed()), (string) $data['deletion_reason']);
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function deleteAction(?string $redirectTo = null): Action
    {
        return Action::make('deleteWithReason')
            ->label('Eliminar')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (ProspectAgent $record): bool => ! $record->trashed())
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalIconColor('danger')
            ->modalHeading(fn (ProspectAgent $record): string => 'Eliminar «'.(trim((string) $record->name) ?: 'Prospecto #'.$record->getKey()).'»')
            ->modalDescription('El prospecto dejará de verse en Captación, en sus indicadores y gráficos. No se borra de la base: se puede consultar y restaurar desde el filtro «Eliminados».')
            ->modalSubmitActionLabel('Eliminar')
            ->modalCancelActionLabel('Cancelar')
            ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes([
                'class' => FilamentIosButton::extraClassForFilamentColor('danger'),
            ]))
            ->schema([self::reasonField()])
            ->action(function (ProspectAgent $record, array $data, Component $livewire) use ($redirectTo): void {
                if (self::runDelete(collect([$record]), (string) $data['deletion_reason']) && $redirectTo !== null) {
                    $livewire->redirect($redirectTo, navigate: true);
                }
            });
    }

    public static function restoreAction(): Action
    {
        return Action::make('restoreProspect')
            ->label('Restaurar')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('success')
            ->visible(fn (ProspectAgent $record): bool => $record->trashed())
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
            ->modalHeading('Restaurar prospecto')
            ->modalDescription(fn (ProspectAgent $record): string => self::restoreDescription($record))
            ->modalSubmitActionLabel('Restaurar')
            ->modalCancelActionLabel('Cancelar')
            ->action(function (ProspectAgent $record): void {
                self::runRestore(collect([$record]));
            });
    }

    public static function bulkRestore(): BulkAction
    {
        return BulkAction::make('restoreSelected')
            ->label('Restaurar seleccionados')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Restaurar prospectos seleccionados')
            ->modalDescription('Los prospectos eliminados volverán a Captación con sus contactos, sucursales, zonas, tareas y notas. Los que no estén eliminados se omiten.')
            ->modalSubmitActionLabel('Restaurar')
            ->modalCancelActionLabel('Cancelar')
            ->action(function (Collection $records): void {
                self::runRestore($records->filter(fn (ProspectAgent $record): bool => $record->trashed()));
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function reasonField(): Textarea
    {
        return Textarea::make('deletion_reason')
            ->label('Motivo de la eliminación')
            ->placeholder('Ej. Registro duplicado del prospecto «Viajes Caribe»; se conserva el del 12/10.')
            ->helperText('Obligatorio. Explica por qué se elimina: quedará en la bitácora del prospecto.')
            ->required()
            ->minLength(ProspectAgentDeletion::MIN_REASON_LENGTH)
            ->maxLength(ProspectAgentDeletion::MAX_REASON_LENGTH)
            ->validationMessages([
                'required' => 'Indica el motivo de la eliminación.',
                'min' => 'El motivo debe tener al menos '.ProspectAgentDeletion::MIN_REASON_LENGTH.' caracteres.',
            ])
            ->rows(4)
            ->autosize();
    }

    /**
     * @param  Collection<int, ProspectAgent>  $records
     */
    private static function runDelete(Collection $records, string $reason): bool
    {
        if ($records->isEmpty()) {
            Notification::make()
                ->warning()
                ->title('No hay prospectos para eliminar')
                ->body('Los registros seleccionados ya estaban eliminados.')
                ->send();

            return false;
        }

        try {
            $deleted = ProspectAgentDeletion::delete($records, $reason, self::actorName());
        } catch (Throwable $exception) {
            Log::error('Captación: no se pudo eliminar prospectos', [
                'ids' => $records->map(fn ($model): mixed => $model->getKey())->values()->all(),
                'error' => $exception->getMessage(),
            ]);

            Notification::make()
                ->danger()
                ->title('No se pudo eliminar')
                ->body('No se eliminó ningún prospecto. Inténtalo de nuevo; si el problema continúa, avisa a sistemas.')
                ->send();

            return false;
        }

        Notification::make()
            ->success()
            ->title($deleted === 1 ? 'Prospecto eliminado' : $deleted.' prospectos eliminados')
            ->body('Puedes consultarlos o restaurarlos desde el filtro «Eliminados».')
            ->send();

        return true;
    }

    /**
     * @param  Collection<int, ProspectAgent>  $records
     */
    private static function runRestore(Collection $records): void
    {
        if ($records->isEmpty()) {
            Notification::make()
                ->warning()
                ->title('No hay prospectos para restaurar')
                ->body('Ninguno de los registros seleccionados está eliminado.')
                ->send();

            return;
        }

        try {
            $restored = ProspectAgentDeletion::restore($records, self::actorName());
        } catch (Throwable $exception) {
            Log::error('Captación: no se pudo restaurar prospectos', [
                'ids' => $records->map(fn ($model): mixed => $model->getKey())->values()->all(),
                'error' => $exception->getMessage(),
            ]);

            Notification::make()
                ->danger()
                ->title('No se pudo restaurar')
                ->body('No se restauró ningún prospecto. Inténtalo de nuevo; si el problema continúa, avisa a sistemas.')
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title($restored === 1 ? 'Prospecto restaurado' : $restored.' prospectos restaurados')
            ->body('Volvieron a Captación con todos sus datos.')
            ->send();
    }

    private static function restoreDescription(ProspectAgent $record): string
    {
        $description = 'El prospecto volverá a Captación con sus contactos, sucursales, zonas, tareas y notas.';

        if (filled($record->deletion_reason)) {
            $description .= ' Fue eliminado'
                .(filled($record->deleted_by) ? ' por '.$record->deleted_by : '')
                .($record->deleted_at !== null ? ' el '.$record->deleted_at->format('d/m/Y H:i') : '')
                .'. Motivo: '.$record->deletion_reason;
        }

        return $description;
    }

    private static function actorName(): string
    {
        return (string) (Auth::user()?->name ?? 'Sistema');
    }
}
