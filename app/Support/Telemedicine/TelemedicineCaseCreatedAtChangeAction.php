<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineCase;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Acción Filament reutilizable para cambiar {@see TelemedicineCase::$created_at}
 * con motivo registrado en bitácora.
 */
final class TelemedicineCaseCreatedAtChangeAction
{
    public static function make(?TelemedicineCase $case = null): Action
    {
        return Action::make('changeCaseCreatedAt')
            ->label('Cambiar fecha de creación')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('warning')
            ->modalHeading(function (?TelemedicineCase $record = null) use ($case): string {
                $target = self::resolveCase($record, $case);

                return 'Cambiar fecha de creación — caso '.($target?->code ?? '#'.($target?->getKey() ?? '—'));
            })
            ->modalDescription('Actualiza la fecha de registro del caso. Debes indicar el motivo; quedará registrado en la bitácora de observaciones.')
            ->modalIcon(Heroicon::OutlinedCalendarDays)
            ->modalIconColor('warning')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Guardar nueva fecha')
            ->modalCancelActionLabel('Cancelar')
            ->modalSubmitAction(
                fn (Action $action): Action => $action
                    ->color('warning')
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
                    ])
            )
            ->modalCancelAction(
                fn (Action $action): Action => $action
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                    ])
            )
            ->closeModalByClickingAway(false)
            ->fillForm(function (?TelemedicineCase $record = null) use ($case): array {
                $target = self::resolveCase($record, $case);

                return [
                    'created_at' => $target?->created_at,
                ];
            })
            ->form([
                DateTimePicker::make('created_at')
                    ->label('Nueva fecha de creación')
                    ->helperText('No puede ser una fecha futura.')
                    ->seconds(true)
                    ->native(false)
                    ->required()
                    ->maxDate(now())
                    ->columnSpanFull(),
                Textarea::make('change_reason')
                    ->label('Motivo del cambio')
                    ->placeholder('Ej.: Corrección administrativa por registro tardío, ajuste de fecha real de apertura del caso…')
                    ->helperText('Campo obligatorio. Mínimo 10 caracteres. Se guarda en la bitácora del caso.')
                    ->required()
                    ->minLength(10)
                    ->maxLength(5000)
                    ->rows(4)
                    ->columnSpanFull()
                    ->validationMessages([
                        'required' => 'Debes indicar el motivo del cambio de fecha.',
                        'minLength' => 'El motivo debe tener al menos 10 caracteres.',
                    ]),
            ])
            ->action(function (array $data, ?TelemedicineCase $record = null) use ($case): void {
                $target = self::resolveCase($record, $case);

                if (! $target instanceof TelemedicineCase) {
                    Notification::make()
                        ->title('No se pudo cambiar la fecha')
                        ->body('No se encontró el caso de telemedicina.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $result = TelemedicineCaseCreatedAtUpdater::execute(
                        $target,
                        $data['created_at'],
                        (string) ($data['change_reason'] ?? ''),
                        Auth::user(),
                    );
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->title('No se pudo cambiar la fecha')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                $newDate = $result['case']->created_at?->format('d/m/Y H:i:s') ?? '—';

                Notification::make()
                    ->title('Fecha de creación actualizada')
                    ->body('El caso quedó con fecha de creación '.$newDate.'. El cambio quedó registrado en la bitácora.')
                    ->success()
                    ->send();
            });
    }

    private static function resolveCase(?TelemedicineCase $record, ?TelemedicineCase $fallback): ?TelemedicineCase
    {
        if ($record instanceof TelemedicineCase) {
            return $record;
        }

        return $fallback;
    }
}
