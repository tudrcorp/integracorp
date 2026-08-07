<?php

declare(strict_types=1);

namespace App\Filament\Shared\CommercialStructure\Actions;

use App\Models\Agency;
use App\Models\Agent;
use App\Support\Filament\CommercialStructureEmailUpdater;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Throwable;

final class UpdateCommercialStructureEmailAction
{
    public static function make(string $entity, string $panel): Action
    {
        $isAgency = $entity === 'agency';
        $entityLabel = $isAgency ? 'agencia' : 'agente';

        return Action::make('updateCommercialEmail')
            ->label('Editar correo')
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('info')
            ->modalHeading('Editar correo de '.$entityLabel)
            ->modalDescription('El cambio quedará registrado en las trazas de seguridad. Puede indicar si también debe actualizarse el correo en usuarios.')
            ->modalIcon(Heroicon::OutlinedEnvelope)
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Guardar correo')
            ->modalCancelActionLabel('Cancelar')
            ->closeModalByClickingAway(false)
            ->fillForm(fn (Agency|Agent $record): array => [
                'email' => (string) ($record->email ?? ''),
                'also_update_user' => false,
                'reason' => '',
            ])
            ->form([
                TextInput::make('email')
                    ->label('Nuevo correo')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->helperText('Correo principal de la '.$entityLabel.'.'),
                Checkbox::make('also_update_user')
                    ->label('Actualizar también el correo en la tabla de usuarios')
                    ->helperText('Marque esta opción si el analista de negocio confirma que el usuario del sistema debe usar el mismo correo nuevo.'),
                Textarea::make('reason')
                    ->label('Motivo del cambio')
                    ->placeholder('Explique por qué se actualiza el correo…')
                    ->helperText('Campo obligatorio. Mínimo 10 caracteres. Quedará en las trazas de seguridad.')
                    ->required()
                    ->minLength(10)
                    ->maxLength(5000)
                    ->rows(4)
                    ->columnSpanFull()
                    ->validationMessages([
                        'required' => 'Debe indicar el motivo del cambio de correo.',
                        'minLength' => 'El motivo debe tener al menos 10 caracteres.',
                    ]),
            ])
            ->action(function (Agency|Agent $record, array $data) use ($entity, $panel): void {
                try {
                    $result = CommercialStructureEmailUpdater::update(
                        record: $record,
                        newEmail: (string) ($data['email'] ?? ''),
                        reason: (string) ($data['reason'] ?? ''),
                        alsoUpdateUser: (bool) ($data['also_update_user'] ?? false),
                        entity: $entity,
                        panel: $panel,
                    );

                    $body = 'Correo actualizado de '.$result['email_from'].' a '.$result['email_to'].'.';

                    if ($result['user_updated']) {
                        $body .= ' También se actualizó el usuario del sistema.';
                    }

                    Notification::make()
                        ->title('Correo actualizado')
                        ->body($body)
                        ->success()
                        ->send();
                } catch (ValidationException $exception) {
                    throw $exception;
                } catch (Throwable $throwable) {
                    Notification::make()
                        ->title('No se pudo actualizar el correo')
                        ->body($throwable->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
