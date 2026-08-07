<?php

declare(strict_types=1);

namespace App\Filament\Shared\CommercialStructure\Actions;

use App\Models\Agency;
use App\Models\Agent;
use App\Support\Filament\CommercialStructureEmailUpdater;
use App\Support\Filament\CommercialStructurePasswordResetter;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ResetCommercialStructureUserPasswordAction
{
    public static function make(string $entity, string $panel): Action
    {
        $isAgency = $entity === 'agency';
        $entityLabel = $isAgency ? 'agencia' : 'agente';

        return Action::make('resetCommercialUserPassword')
            ->label('Resetear contraseña')
            ->icon(Heroicon::OutlinedKey)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Resetear contraseña de usuario')
            ->modalDescription(
                'Se asignará la contraseña temporal '.CommercialStructureEmailUpdater::TEMPORARY_PASSWORD
                .' al usuario cuyo correo coincida con el de la '.$entityLabel
                .'. El motivo quedará en las trazas de seguridad.'
            )
            ->modalIcon(Heroicon::OutlinedKey)
            ->modalIconColor('warning')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Sí, resetear')
            ->modalCancelActionLabel('Cancelar')
            ->closeModalByClickingAway(false)
            ->visible(fn (Agency|Agent $record): bool => CommercialStructureEmailUpdater::emailsMatchForPasswordReset($record))
            ->form([
                Textarea::make('reason')
                    ->label('Motivo del reseteo')
                    ->placeholder('Explique por qué se resetea la contraseña…')
                    ->helperText('Campo obligatorio. Mínimo 10 caracteres. Quedará en las trazas de seguridad.')
                    ->required()
                    ->minLength(10)
                    ->maxLength(5000)
                    ->rows(4)
                    ->columnSpanFull()
                    ->validationMessages([
                        'required' => 'Debe indicar el motivo del reseteo de contraseña.',
                        'minLength' => 'El motivo debe tener al menos 10 caracteres.',
                    ]),
            ])
            ->action(function (Agency|Agent $record, array $data) use ($entity, $panel): void {
                try {
                    $result = CommercialStructurePasswordResetter::reset(
                        record: $record,
                        reason: (string) ($data['reason'] ?? ''),
                        entity: $entity,
                        panel: $panel,
                    );

                    Notification::make()
                        ->title('Contraseña reseteada')
                        ->body(
                            'Se asignó la contraseña temporal '
                            .CommercialStructureEmailUpdater::TEMPORARY_PASSWORD
                            .' al usuario '.$result['email'].'.'
                        )
                        ->success()
                        ->send();
                } catch (ValidationException $exception) {
                    throw $exception;
                } catch (Throwable $throwable) {
                    Notification::make()
                        ->title('No se pudo resetear la contraseña')
                        ->body($throwable->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
