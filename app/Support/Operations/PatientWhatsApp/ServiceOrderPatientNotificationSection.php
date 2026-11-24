<?php

declare(strict_types=1);

namespace App\Support\Operations\PatientWhatsApp;

use App\Models\OperationServiceOrder;
use App\Models\ServiceOrderPatientNotification as PatientNotification;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\UserNavigationAccess;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Sección «WhatsApp al paciente» de la ficha de la orden de servicio: a quién se le escribió,
 * cuándo, qué respondió y el botón «Reenviar» (permiso propio).
 */
final class ServiceOrderPatientNotificationSection
{
    public static function notificationFor(OperationServiceOrder $order): ?PatientNotification
    {
        return $order->relationLoaded('patientWhatsAppNotification')
            ? $order->getRelation('patientWhatsAppNotification')
            : $order->patientWhatsAppNotification()->first();
    }

    public static function canResend(): bool
    {
        $user = Auth::user();

        return $user !== null
            && (UserNavigationAccess::isSuperAdmin($user)
                || BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::RESEND_SERVICE_ORDER_PATIENT_WHATSAPP));
    }

    public static function make(string $sectionCardClass): Section
    {
        return Section::make('WhatsApp al paciente')
            ->description('Aviso al paciente (o a su representante, si es menor de edad) con el documento de la orden y la pregunta SI/NO.')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->extraAttributes(['class' => $sectionCardClass])
            ->visible(fn (OperationServiceOrder $record): bool => self::notificationFor($record) instanceof PatientNotification)
            ->headerActions([self::resendAction()])
            ->schema([
                TextEntry::make('patient_whatsapp_status')
                    ->label('Estatus')
                    ->badge()
                    ->state(fn (OperationServiceOrder $record): ?string => self::notificationFor($record)?->statusLabel())
                    ->color(fn (OperationServiceOrder $record): string => self::notificationFor($record)?->statusColor() ?? 'gray'),
                TextEntry::make('patient_whatsapp_answer')
                    ->label('Respuesta del paciente')
                    ->badge()
                    ->placeholder('Sin respuesta')
                    ->state(fn (OperationServiceOrder $record): ?string => self::notificationFor($record)?->answer)
                    ->color(fn (?string $state): string => $state === PatientNotification::ANSWER_NO ? 'danger' : 'success'),
                TextEntry::make('patient_whatsapp_delivery')
                    ->label('¿Le llegó?')
                    ->badge()
                    ->placeholder('Aún no se ha enviado')
                    ->state(fn (OperationServiceOrder $record): ?string => self::notificationFor($record)?->deliveryLabel())
                    ->color(fn (OperationServiceOrder $record): string => self::notificationFor($record)?->deliveryColor() ?? 'gray')
                    ->helperText(fn (OperationServiceOrder $record): ?string => self::deliveryHelp(self::notificationFor($record))),
                TextEntry::make('patient_whatsapp_recipient')
                    ->label('Enviado a')
                    ->placeholder('-')
                    ->state(fn (OperationServiceOrder $record): ?string => self::recipientLabel(self::notificationFor($record))),
                TextEntry::make('patient_whatsapp_sent_at')
                    ->label('Enviado el')
                    ->placeholder('-')
                    ->state(fn (OperationServiceOrder $record): ?string => self::notificationFor($record)?->sent_at?->format('d/m/Y h:i A')),
                TextEntry::make('patient_whatsapp_delivered_at')
                    ->label('Llegó al teléfono el')
                    ->placeholder('-')
                    ->visible(fn (OperationServiceOrder $record): bool => self::notificationFor($record)?->deliveredMoment() !== null)
                    ->state(fn (OperationServiceOrder $record): ?string => self::notificationFor($record)?->deliveredMoment()?->format('d/m/Y h:i A')),
                TextEntry::make('patient_whatsapp_read_at')
                    ->label('Lo leyó el')
                    ->placeholder('-')
                    ->visible(fn (OperationServiceOrder $record): bool => self::notificationFor($record)?->read_at !== null)
                    ->state(fn (OperationServiceOrder $record): ?string => self::notificationFor($record)?->read_at?->format('d/m/Y h:i A')),
                TextEntry::make('patient_whatsapp_answered_at')
                    ->label('Respondió el')
                    ->placeholder('-')
                    ->state(fn (OperationServiceOrder $record): ?string => self::notificationFor($record)?->answered_at?->format('d/m/Y h:i A')),
                TextEntry::make('patient_whatsapp_expires_at')
                    ->label('Vence sin respuesta el')
                    ->placeholder('-')
                    ->visible(fn (OperationServiceOrder $record): bool => self::notificationFor($record)?->status === PatientNotification::STATUS_SENT)
                    ->state(fn (OperationServiceOrder $record): ?string => self::notificationFor($record)?->expires_at?->format('d/m/Y h:i A')),
                TextEntry::make('patient_whatsapp_answer_raw')
                    ->label('Texto que escribió')
                    ->placeholder('-')
                    ->visible(fn (OperationServiceOrder $record): bool => filled(self::notificationFor($record)?->answer_raw))
                    ->state(fn (OperationServiceOrder $record): ?string => self::notificationFor($record)?->answer_raw),
                TextEntry::make('patient_whatsapp_waiting')
                    ->label('Por qué espera')
                    ->visible(fn (OperationServiceOrder $record): bool => self::notificationFor($record)?->status === PatientNotification::STATUS_WAITING_TURN)
                    ->state('A este número ya se le preguntó por otra orden. Este mensaje sale en cuanto responda esa o venza el plazo, para que cada respuesta corresponda a una sola orden.')
                    ->columnSpanFull(),
                TextEntry::make('patient_whatsapp_error')
                    ->label('Detalle')
                    ->color('danger')
                    ->visible(fn (OperationServiceOrder $record): bool => filled(self::notificationFor($record)?->error))
                    ->state(fn (OperationServiceOrder $record): ?string => self::notificationFor($record)?->error)
                    ->columnSpanFull(),
            ])
            ->columns(3)
            ->columnSpanFull();
    }

    public static function resendAction(): Action
    {
        return Action::make('resendPatientWhatsApp')
            ->label('Reenviar WhatsApp')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('primary')
            ->visible(fn (OperationServiceOrder $record): bool => self::canResend() && (self::notificationFor($record)?->isResendable() ?? false))
            ->requiresConfirmation()
            ->modalHeading('Reenviar WhatsApp al paciente')
            ->modalDescription('Se vuelve a tomar el teléfono de la ficha del paciente o del caso (por si lo corrigió) y se envía de nuevo el documento con la pregunta SI/NO.')
            ->modalSubmitActionLabel('Reenviar')
            ->action(function (OperationServiceOrder $record): void {
                $notification = self::notificationFor($record);
                $user = Auth::user();

                if (! $notification instanceof PatientNotification || $user === null || ! self::canResend()) {
                    Notification::make()->title('No tiene permiso para reenviar este WhatsApp.')->danger()->send();

                    return;
                }

                try {
                    $fresh = ServiceOrderPatientNotifier::resend($notification, $user);
                } catch (RuntimeException $exception) {
                    Notification::make()->title('No se reenvió')->body($exception->getMessage())->danger()->send();

                    return;
                }

                $record->unsetRelation('patientWhatsAppNotification');

                Notification::make()
                    ->title('WhatsApp en camino')
                    ->body($fresh->status === PatientNotification::STATUS_WAITING_TURN
                        ? 'Quedó en espera: a ese número ya se le preguntó por otra orden y saldrá en cuanto responda.'
                        : 'Se está enviando a '.self::recipientLabel($fresh).'.')
                    ->success()
                    ->send();
            });
    }

    public static function deliveryHelp(?PatientNotification $notification): ?string
    {
        if (! $notification instanceof PatientNotification || ! $notification->wasSent()) {
            return null;
        }

        return match ($notification->delivery_status) {
            PatientNotification::DELIVERY_READ => 'Lo abrió en su teléfono.',
            PatientNotification::DELIVERY_DEVICE => 'Llegó a su teléfono, aunque todavía no lo haya abierto. «Leído» solo aparece si tiene activadas las confirmaciones de lectura.',
            PatientNotification::DELIVERY_SERVER => 'WhatsApp lo recibió, pero el teléfono del paciente no lo ha descargado (apagado o sin internet). Llegará cuando se conecte.',
            default => 'WhatsApp aún no confirma la entrega. Se actualiza sola en unos minutos.',
        };
    }

    public static function recipientLabel(?PatientNotification $notification): ?string
    {
        if (! $notification instanceof PatientNotification || blank($notification->phone)) {
            return null;
        }

        $who = $notification->recipient_kind === PatientNotification::RECIPIENT_REPRESENTATIVE
            ? 'Representante'.(filled($notification->recipient_name) ? ' ('.$notification->recipient_name.')' : '')
            : (string) ($notification->recipient_name ?: 'Paciente');

        return $who.' · +'.$notification->phone;
    }
}
