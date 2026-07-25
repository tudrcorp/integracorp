<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Resources\TelemedicineCases\Actions;

use App\Models\TelemedicineCase;
use App\Services\TelemedicineCaseReversalService;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ReverseTelemedicineCaseAction
{
    /**
     * @var list<string>
     */
    public const BLOCKED_STATUSES = [
        'ALTA MEDICA',
        'EN SEGUIMIENTO',
    ];

    public static function canReverse(?TelemedicineCase $record): bool
    {
        if ($record === null) {
            return false;
        }

        $status = mb_strtoupper(trim((string) $record->status));

        return ! in_array($status, self::BLOCKED_STATUSES, true);
    }

    public static function make(?callable $beforeReverse = null, ?callable $afterReverse = null): Action
    {
        return Action::make('reverse_case')
            ->label('Reversar caso')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->modalHeading('Reversar caso de telemedicina')
            ->modalDescription('El caso se eliminará para que los analistas de TDG o del proveedor puedan reasignarlo. Debe indicar el motivo del reverso; se notificará por correo y WhatsApp.')
            ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
            ->modalIconColor('danger')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Sí, reversar caso')
            ->modalCancelActionLabel('Cancelar')
            ->closeModalByClickingAway(false)
            ->modalSubmitAction(
                fn (Action $action) => $action
                    ->color('danger')
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('danger'),
                    ])
            )
            ->modalCancelAction(
                fn (Action $action) => $action
                    ->color('gray')
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                    ])
            )
            ->form([
                Textarea::make('reversal_note')
                    ->label('Nota / observación del reverso')
                    ->placeholder('Explique por qué se reversa el caso (ej.: no corresponde especialidad, datos incorrectos, solicitud del paciente…)')
                    ->helperText('Campo obligatorio. Mínimo 10 caracteres. Esta nota se destacará en la notificación a analistas.')
                    ->required()
                    ->minLength(10)
                    ->maxLength(5000)
                    ->rows(5)
                    ->columnSpanFull()
                    ->validationMessages([
                        'required' => 'Debe indicar el motivo del reverso.',
                        'minLength' => 'La nota debe tener al menos 10 caracteres.',
                    ]),
            ])
            ->visible(fn (?TelemedicineCase $record): bool => self::canReverse($record))
            ->action(function (TelemedicineCase $record, array $data) use ($beforeReverse, $afterReverse): mixed {
                if (! self::canReverse($record)) {
                    Notification::make()
                        ->title('No se puede reversar el caso')
                        ->body('Los casos en seguimiento o con alta médica no pueden ser reversados.')
                        ->warning()
                        ->send();

                    return null;
                }

                if ($beforeReverse !== null && $beforeReverse($record) === false) {
                    return null;
                }

                try {
                    $payload = app(TelemedicineCaseReversalService::class)->reverse(
                        $record,
                        (string) ($data['reversal_note'] ?? ''),
                    );

                    Notification::make()
                        ->title('Caso reversado')
                        ->body('El caso '.$payload['case_code'].' fue eliminado. Los analistas recibirán la notificación para reasignarlo.')
                        ->success()
                        ->send();

                    if ($afterReverse !== null) {
                        return $afterReverse($payload);
                    }

                    return null;
                } catch (Throwable $exception) {
                    Log::error('ReverseTelemedicineCaseAction: error', [
                        'telemedicine_case_id' => $record->id,
                        'message' => $exception->getMessage(),
                    ]);

                    Notification::make()
                        ->title('No se pudo reversar el caso')
                        ->body('Ocurrió un error al reversar el caso. Intente de nuevo o contacte a soporte.')
                        ->danger()
                        ->send();

                    return null;
                }
            });
    }
}
