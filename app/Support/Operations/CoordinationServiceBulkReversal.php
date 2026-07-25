<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationCoordinationService;
use App\Services\OperationCoordinationServiceReversalService;
use App\Support\Filament\FilamentIosButton;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Throwable;

/**
 * Bulk action para reversar servicios de coordinación en estatus PENDIENTE.
 */
final class CoordinationServiceBulkReversal
{
    public static function makeBulkAction(): BulkAction
    {
        return BulkAction::make('reverse_coordination_services')
            ->label('Reversar servicios')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->modalHeading('Reversar servicios de coordinación')
            ->modalDescription(fn (): HtmlString => new HtmlString(
                '<p class="text-sm text-gray-600 dark:text-gray-300">'
                .'Solo se pueden reversar servicios en estatus <span class="font-semibold text-gray-900 dark:text-white">PENDIENTE</span>. '
                .'Si en la selección existe alguno en gestión u otro estatus, la acción no se ejecutará. '
                .'Debe indicar el motivo; el servicio se eliminará y quedará registrado en la bitácora del caso y en el log de seguridad.'
                .'</p>'
            ))
            ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
            ->modalIconColor('danger')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Sí, reversar servicios')
            ->modalCancelActionLabel('Cancelar')
            ->deselectRecordsAfterCompletion()
            ->closeModalByClickingAway(false)
            ->modalSubmitAction(
                fn (Action $action): Action => $action
                    ->color('danger')
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('danger'),
                    ])
            )
            ->modalCancelAction(
                fn (Action $action): Action => $action
                    ->color('gray')
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                    ])
            )
            ->form([
                Textarea::make('reversal_note')
                    ->label('Observación / motivo del reverso')
                    ->placeholder('Explique por qué se reversa el servicio (ej.: duplicado por error, solicitud del paciente, no aplica…)')
                    ->helperText('Campo obligatorio. Mínimo 10 caracteres. Se registrará en la bitácora del caso y en el log de seguridad.')
                    ->required()
                    ->minLength(10)
                    ->maxLength(5000)
                    ->rows(5)
                    ->columnSpanFull()
                    ->validationMessages([
                        'required' => 'Debe indicar el motivo del reverso.',
                        'minLength' => 'La observación debe tener al menos 10 caracteres.',
                    ]),
            ])
            ->action(function (EloquentCollection|Collection $records, array $data): void {
                $services = Collection::make($records)
                    ->filter(fn (mixed $record): bool => $record instanceof OperationCoordinationService)
                    ->values();

                if ($services->isEmpty()) {
                    Notification::make()
                        ->title('Sin servicios seleccionados')
                        ->body('Seleccione al menos un servicio en estatus PENDIENTE para reversar.')
                        ->warning()
                        ->send();

                    return;
                }

                $serviceIds = $services->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all();

                try {
                    $result = app(OperationCoordinationServiceReversalService::class)->reverseMany(
                        $services,
                        (string) ($data['reversal_note'] ?? ''),
                    );

                    $count = $result['reversed_count'];

                    Notification::make()
                        ->title($count === 1 ? 'Servicio reversado' : 'Servicios reversados')
                        ->body($count === 1
                            ? 'El servicio pendiente fue eliminado. El motivo quedó en la bitácora del caso y en el log de seguridad.'
                            : "Se reversaron {$count} servicios pendientes. El motivo quedó en la bitácora de cada caso y en el log de seguridad.")
                        ->success()
                        ->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->title('No se puede reversar')
                        ->body($exception->getMessage())
                        ->warning()
                        ->send();
                } catch (Throwable $exception) {
                    Log::error('CoordinationServiceBulkReversal: error', [
                        'operation_coordination_service_ids' => $serviceIds,
                        'message' => $exception->getMessage(),
                    ]);

                    SecurityAudit::log(
                        'AUDIT_OPERATIONS_COORDINATION_SERVICE_BULK_REVERSAL_FAILED',
                        'operations.coordination-services.bulk-reverse',
                        [
                            'operation_coordination_service_ids' => $serviceIds,
                            'services_count' => count($serviceIds),
                            'error' => $exception->getMessage(),
                        ]
                    );

                    Notification::make()
                        ->title('No se pudo reversar')
                        ->body('Ocurrió un error al reversar los servicios. Intente de nuevo o contacte a soporte.')
                        ->danger()
                        ->send();
                }
            });
    }
}
