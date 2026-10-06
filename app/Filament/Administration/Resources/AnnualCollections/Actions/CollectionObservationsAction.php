<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AnnualCollections\Actions;

use App\Models\Collection;
use App\Models\CollectionObservation;
use App\Models\User;
use App\Support\Collections\CollectionObservationLog;
use App\Support\Collections\CollectionReceivableReport;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Observaciones de cobranza de una fila de «Cobranza Por Mes»: el analista
 * registra su gestión y ve la bitácora completa de la afiliación, no solo la de
 * la cuota visible.
 */
final class CollectionObservationsAction
{
    public static function make(): Action
    {
        return Action::make('collectionObservations')
            ->label('Observaciones')
            ->tooltip('Registrar una gestión de cobranza y ver la bitácora de la afiliación')
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->color('gray')
            ->badge(fn (Collection $record): ?int => self::observationsCount($record) ?: null)
            ->badgeColor('info')
            ->slideOver()
            ->modalWidth(Width::TwoExtraLarge)
            ->modalHeading(fn (Collection $record): string => 'Observaciones · '.$record->affiliation_code)
            ->modalDescription(fn (Collection $record): string => self::describe($record))
            ->modalIcon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->modalSubmitActionLabel('Guardar observación')
            ->modalCancelActionLabel('Cerrar')
            ->closeModalByClickingAway(false)
            ->schema(fn (Collection $record): array => [
                Textarea::make('observation')
                    ->label('Nueva observación')
                    ->placeholder('Ej.: Se llamó al pagador; promete pagar el viernes por transferencia.')
                    ->helperText('Queda en la bitácora con su nombre, la fecha y la cuota actual. No se puede editar ni borrar después.')
                    ->rows(4)
                    ->required()
                    ->maxLength(CollectionObservation::MAX_LENGTH)
                    ->validationMessages([
                        'required' => 'Escriba la observación antes de guardarla.',
                        'max' => 'La observación no puede superar '.CollectionObservation::MAX_LENGTH.' caracteres.',
                    ]),
                Placeholder::make('observation_log')
                    ->label('Bitácora de la afiliación')
                    ->content(fn () => view('filament.administration.annual-collections.observation-log', [
                        'observations' => CollectionObservationLog::forAffiliation($record->affiliation_code),
                        'currentCollectionId' => (int) $record->id,
                    ])),
            ])
            ->action(function (Collection $record, array $data, Action $action): void {
                $user = Auth::user();

                if (! $user instanceof User) {
                    Notification::make()
                        ->title('Sesión inválida')
                        ->body('Vuelva a iniciar sesión para registrar la observación.')
                        ->danger()
                        ->send();

                    $action->halt();
                }

                try {
                    $observation = CollectionObservationLog::record($record, (string) ($data['observation'] ?? ''), $user);
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->title('No se guardó la observación')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    $action->halt();
                } catch (Throwable $exception) {
                    Log::error('CollectionObservationsAction: error al guardar', [
                        'collection_id' => $record->id,
                        'affiliation_code' => $record->affiliation_code,
                        'message' => $exception->getMessage(),
                    ]);

                    Notification::make()
                        ->title('No se guardó la observación')
                        ->body('Ocurrió un error inesperado. Intente de nuevo; si persiste, reporte a soporte.')
                        ->danger()
                        ->send();

                    $action->halt();
                }

                SecurityAudit::log('AUDIT_ADMIN_RECEIVABLE_OBSERVATION_CREATED', 'administration.annual-collections.observations', [
                    'collection_observation_id' => $observation->id,
                    'collection_id' => $record->id,
                    'affiliation_code' => $record->affiliation_code,
                ]);

                Notification::make()
                    ->title('Observación guardada')
                    ->body('Quedó registrada en la bitácora de '.$record->affiliation_code.'.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Viene precargado con `withCount` en la consulta de la tabla; el respaldo
     * solo corre si la acción se usa fuera de ella.
     */
    private static function observationsCount(Collection $record): int
    {
        $count = $record->getAttribute('receivable_observations_count');

        return $count !== null
            ? (int) $count
            : (int) $record->receivableObservations()->count();
    }

    private static function describe(Collection $record): string
    {
        $parts = array_filter([
            CollectionReceivableReport::holderName($record),
            ($dueDate = CollectionReceivableReport::dueDate($record)) !== null
                ? 'Cuota actual: vence el '.$dueDate->format('d/m/Y')
                : null,
            ($amount = CollectionReceivableReport::installmentAmount($record)) !== null
                ? 'US$ '.number_format($amount, 2, ',', '.')
                : null,
        ]);

        return implode(' · ', $parts);
    }
}
