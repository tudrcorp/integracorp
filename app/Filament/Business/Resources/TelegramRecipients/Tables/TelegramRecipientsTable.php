<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\TelegramRecipients\Tables;

use App\Enums\TelegramRecipientStatus;
use App\Enums\TelegramRecipientType;
use App\Filament\Business\Resources\TelegramRecipients\Pages\ListTelegramRecipients;
use App\Models\TelegramRecipient;
use App\Support\InternalNotifications\InternalNotificationDispatcher;
use App\Support\SecurityAudit;
use App\Support\Telegram\TelegramBotClient;
use App\Support\Telegram\TelegramLinkService;
use App\Support\Telegram\TelegramText;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Throwable;

final class TelegramRecipientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->poll('15s')
            ->emptyStateIcon('heroicon-o-paper-airplane')
            ->emptyStateHeading('Todavía no hay destinos de Telegram')
            ->emptyStateDescription('El personal se vincula desde su menú de usuario → «Conectar Telegram». Los grupos aparecen aquí al agregar el bot en Telegram. Los contactos externos se agregan con el botón de arriba.')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->weight('bold')
                    ->description(fn (TelegramRecipient $record): ?string => filled($record->telegram_username) ? '@'.$record->telegram_username : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (TelegramRecipientType $state): string => $state->label())
                    ->color(fn (TelegramRecipientType $state): string => match ($state) {
                        TelegramRecipientType::User => 'info',
                        TelegramRecipientType::External => 'warning',
                        TelegramRecipientType::Group => 'success',
                    }),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (TelegramRecipientStatus $state): string => $state->label())
                    ->color(fn (TelegramRecipientStatus $state): string => $state->color())
                    ->tooltip(fn (TelegramRecipient $record): ?string => $record->last_error),
                TextColumn::make('contact')
                    ->label('Correo / teléfono')
                    ->state(fn (TelegramRecipient $record): ?string => collect([$record->email ?: $record->user?->email, $record->phone ?: $record->user?->phone])->filter()->implode(' · ') ?: null)
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('reason')
                    ->label('Motivo')
                    ->limit(50)
                    ->tooltip(fn (TelegramRecipient $record): ?string => $record->reason)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('linked_at')
                    ->label('Vinculado')
                    ->dateTime('d/m/Y h:i A')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('last_sent_at')
                    ->label('Último aviso')
                    ->since()
                    ->dateTimeTooltip('d/m/Y h:i A')
                    ->placeholder('Nunca')
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label('Vence')
                    ->date('d/m/Y')
                    ->color(fn (TelegramRecipient $record): ?string => $record->isExpired() ? 'danger' : null)
                    ->placeholder('Sin vencimiento')
                    ->toggleable(),
                TextColumn::make('approver.name')
                    ->label('Aprobado por')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(collect(TelegramRecipientStatus::cases())->mapWithKeys(fn (TelegramRecipientStatus $status): array => [$status->value => $status->label()])->all()),
            ])
            ->recordActions([
                ActionGroup::make([
                    self::approveGroupAction(),
                    self::sendTestAction(),
                    self::generateLinkAction(),
                    self::editExternalAction(),
                    self::reactivateGroupAction(),
                    self::disableAction(),
                ]),
            ]);
    }

    private static function approveGroupAction(): Action
    {
        return Action::make('approveGroup')
            ->label('Aprobar grupo')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (TelegramRecipient $record): bool => $record->type === TelegramRecipientType::Group && $record->status === TelegramRecipientStatus::PendingApproval)
            ->requiresConfirmation()
            ->modalHeading(fn (TelegramRecipient $record): string => 'Aprobar «'.$record->name.'»')
            ->modalDescription('Desde ahora el grupo podrá elegirse en el Centro de notificaciones y todos sus miembros verán los avisos que se le envíen. Apruébelo solo si conoce a quienes están en él.')
            ->modalSubmitActionLabel('Aprobar')
            ->action(function (TelegramRecipient $record): void {
                $record->forceFill([
                    'status' => TelegramRecipientStatus::Active,
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                    'linked_at' => $record->linked_at ?? now(),
                    'updated_by' => Auth::user()?->name,
                ])->save();

                self::audit('AUDIT_TELEGRAM_GROUP_APPROVED', $record);

                try {
                    TelegramBotClient::make()->sendMessage((string) $record->chat_id, TelegramText::escape('✅ Grupo aprobado en IntegraCorp. Desde ahora recibirá aquí las notificaciones internas que se le asignen.'));
                } catch (Throwable) {
                }

                Notification::make()->title('Grupo aprobado')->body('Ya puede elegirlo en el Centro de notificaciones.')->success()->send();
            });
    }

    private static function sendTestAction(): Action
    {
        return Action::make('sendTest')
            ->label('Enviar mensaje de prueba')
            ->icon('heroicon-o-paper-airplane')
            ->color('info')
            ->visible(fn (TelegramRecipient $record): bool => $record->status === TelegramRecipientStatus::Active)
            ->requiresConfirmation()
            ->modalHeading(fn (TelegramRecipient $record): string => 'Enviar una prueba a «'.$record->name.'»')
            ->modalDescription('Llega en segundos si el worker de la cola de notificaciones está encendido. Si Telegram no lo entrega, sale por WhatsApp o correo y lo verá en la bitácora.')
            ->modalSubmitActionLabel('Enviar')
            ->action(function (TelegramRecipient $record): void {
                InternalNotificationDispatcher::sendTestTo($record, (string) (Auth::user()?->name ?? 'SUPERADMIN'));

                Notification::make()->title('Prueba en camino')->body('Revise el resultado en «Bitácora de envíos».')->success()->send();
            });
    }

    private static function generateLinkAction(): Action
    {
        return Action::make('generateLink')
            ->label('Generar enlace de vinculación')
            ->icon('heroicon-o-link')
            ->color('warning')
            ->visible(fn (TelegramRecipient $record): bool => $record->type === TelegramRecipientType::External && ! $record->isExpired())
            ->requiresConfirmation()
            ->modalHeading('Generar enlace de vinculación')
            ->modalDescription(fn (): string => 'El enlace sirve una sola vez y vence en '.TelegramLinkService::tokenMinutes().' minutos. Si ya había uno sin usar, deja de funcionar. Envíeselo a la persona por un canal de confianza.')
            ->modalSubmitActionLabel('Generar')
            ->action(function (TelegramRecipient $record, ListTelegramRecipients $livewire): void {
                try {
                    $link = TelegramLinkService::issueForExternal($record, (string) (Auth::user()?->name ?? 'SUPERADMIN'));
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title('No se generó el enlace')->body($exception->getMessage())->danger()->send();

                    return;
                }

                $livewire->replaceMountedAction('showTelegramLink', [
                    'name' => $record->name,
                    'url' => $link['url'],
                    'code' => $link['code'],
                    'expires_at' => $link['expires_at']->timezone(config('app.timezone'))->format('h:i A'),
                ]);
            });
    }

    private static function editExternalAction(): Action
    {
        return Action::make('editExternal')
            ->label('Editar contacto')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->visible(fn (TelegramRecipient $record): bool => $record->type === TelegramRecipientType::External)
            ->fillForm(fn (TelegramRecipient $record): array => [
                'name' => $record->name,
                'email' => $record->email,
                'phone' => $record->phone,
                'reason' => $record->reason,
                'expires_at' => $record->expires_at,
            ])
            ->schema(ListTelegramRecipients::externalContactSchema())
            ->modalHeading('Editar contacto externo')
            ->modalSubmitActionLabel('Guardar')
            ->action(function (TelegramRecipient $record, array $data): void {
                $record->forceFill([
                    'name' => trim((string) $data['name']),
                    'email' => filled($data['email'] ?? null) ? mb_strtolower(trim((string) $data['email'])) : null,
                    'phone' => filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null,
                    'reason' => trim((string) $data['reason']),
                    'expires_at' => $data['expires_at'] ?? null,
                    'updated_by' => Auth::user()?->name,
                ])->save();

                self::audit('AUDIT_TELEGRAM_EXTERNAL_UPDATED', $record);

                Notification::make()->title('Contacto actualizado')->success()->send();
            });
    }

    private static function reactivateGroupAction(): Action
    {
        return Action::make('reactivateGroup')
            ->label('Reactivar grupo')
            ->icon('heroicon-o-arrow-path')
            ->color('success')
            ->visible(fn (TelegramRecipient $record): bool => $record->type === TelegramRecipientType::Group && $record->status === TelegramRecipientStatus::Disabled && filled($record->chat_id))
            ->requiresConfirmation()
            ->modalDescription('El grupo vuelve a recibir los avisos que tenga asignados en el Centro de notificaciones.')
            ->action(function (TelegramRecipient $record): void {
                $record->forceFill(['status' => TelegramRecipientStatus::Active, 'approved_by' => Auth::id(), 'approved_at' => now(), 'updated_by' => Auth::user()?->name])->save();
                self::audit('AUDIT_TELEGRAM_GROUP_REACTIVATED', $record);
                Notification::make()->title('Grupo reactivado')->success()->send();
            });
    }

    private static function disableAction(): Action
    {
        return Action::make('disable')
            ->label(fn (TelegramRecipient $record): string => $record->type === TelegramRecipientType::Group ? 'Desactivar grupo' : 'Desvincular')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn (TelegramRecipient $record): bool => in_array($record->status, [TelegramRecipientStatus::Active, TelegramRecipientStatus::PendingApproval, TelegramRecipientStatus::PendingLink, TelegramRecipientStatus::Blocked], true))
            ->requiresConfirmation()
            ->modalHeading(fn (TelegramRecipient $record): string => ($record->type === TelegramRecipientType::Group ? 'Desactivar «' : 'Desvincular a «').$record->name.'»')
            ->modalDescription(fn (TelegramRecipient $record): string => $record->type === TelegramRecipientType::Group
                ? 'El grupo deja de recibir avisos de inmediato. Los avisos que lo tenían elegido saldrán por los contactos del Centro de notificaciones (WhatsApp / correo) mientras no tenga otro grupo.'
                : 'Esta persona deja de recibir avisos por Telegram y los recibirá por WhatsApp o correo. Para volver a usar Telegram tendrá que vincularse de nuevo.')
            ->modalSubmitActionLabel('Sí, desactivar')
            ->action(function (TelegramRecipient $record): void {
                $actor = (string) (Auth::user()?->name ?? 'SUPERADMIN');

                if ($record->type === TelegramRecipientType::Group) {
                    $record->forceFill(['status' => TelegramRecipientStatus::Disabled, 'updated_by' => $actor])->save();
                    self::audit('AUDIT_TELEGRAM_GROUP_DISABLED', $record);
                } else {
                    TelegramLinkService::unlink($record, $actor);
                }

                Notification::make()->title('Listo')->body('Ya no se le enviarán avisos por Telegram.')->success()->send();
            });
    }

    private static function audit(string $action, TelegramRecipient $record): void
    {
        try {
            SecurityAudit::log($action, 'business.telegram', [
                'telegram_recipient_id' => $record->getKey(),
                'type' => $record->type->value,
                'name' => $record->name,
            ]);
        } catch (Throwable) {
        }
    }
}
