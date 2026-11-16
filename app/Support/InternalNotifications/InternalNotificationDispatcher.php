<?php

declare(strict_types=1);

namespace App\Support\InternalNotifications;

use App\Enums\NotificationDeliveryChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\SystemNotificationKey;
use App\Enums\TelegramDeliveryMode;
use App\Enums\TelegramRecipientStatus;
use App\Jobs\SendNotificacionWhatsApp;
use App\Jobs\SendNotificacionWhatsAppDocument;
use App\Jobs\SendTelegramDeliveryJob;
use App\Mail\InternalNotificationMail;
use App\Models\NotificationDelivery;
use App\Models\SystemNotificationRecipientSetting;
use App\Models\TelegramRecipient;
use App\Models\User;
use App\Support\SystemNotificationRecipients;
use App\Support\Telegram\TelegramBotClient;
use App\Support\Telegram\TelegramText;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reparte un aviso interno según el Centro de notificaciones:
 *
 * - Telegram apagado: WhatsApp a los teléfonos y correo a los correos, como siempre.
 * - Persona: Telegram a cada contacto vinculado; los que no tienen Telegram, por WhatsApp / correo.
 * - Grupo: Telegram a los grupos aprobados elegidos.
 * - Ambos: las dos cosas.
 *
 * Cada envío queda escrito en notification_deliveries ANTES de salir. Si
 * Telegram falla, SendTelegramDeliveryJob pasa al respaldo (InternalNotificationFallback).
 * La campanita del panel se envía siempre a los usuarios del aviso.
 *
 * Los paneles corren cada acción dentro de una transacción (databaseTransactions()),
 * así que todo se encola con afterCommit(): un worker nunca toma un envío cuya fila
 * todavía no existe para él.
 */
final class InternalNotificationDispatcher
{
    /**
     * @return array{telegram: int, groups: int, whatsapp: int, mail: int, skipped: int, database: int, paused: bool, mode: TelegramDeliveryMode}
     */
    public static function dispatch(InternalNotification $notification): array
    {
        $setting = SystemNotificationRecipients::setting($notification->key);
        $summary = ['telegram' => 0, 'groups' => 0, 'whatsapp' => 0, 'mail' => 0, 'skipped' => 0, 'database' => 0, 'paused' => false, 'mode' => TelegramDeliveryMode::Off];

        if (! $setting->isActive()) {
            return [...$summary, 'paused' => true];
        }

        $mode = self::effectiveMode($notification, $setting);
        $summary['mode'] = $mode;
        $contacts = InternalContactResolver::resolve($setting, $mode->includesPeople());

        $telegramIds = [];
        $direct = [];

        DB::transaction(function () use ($notification, $setting, $mode, $contacts, &$telegramIds, &$direct, &$summary): void {
            if ($mode->includesPeople()) {
                foreach ($contacts['telegram'] as $recipient) {
                    $telegramIds[] = self::record($notification, NotificationDeliveryChannel::Telegram, NotificationDeliveryStatus::Pending, $recipient->name, $recipient)->getKey();
                    $summary['telegram']++;
                }
            }

            if ($mode->includesGroups()) {
                foreach (self::groups($setting) as $group) {
                    if ($group->status !== TelegramRecipientStatus::Active || blank($group->chat_id)) {
                        self::record($notification, NotificationDeliveryChannel::Telegram, NotificationDeliveryStatus::Skipped, $group->name, $group, 'El grupo no está activo ('.$group->status->label().').');
                        $summary['skipped']++;

                        continue;
                    }

                    $telegramIds[] = self::record($notification, NotificationDeliveryChannel::Telegram, NotificationDeliveryStatus::Pending, $group->name, $group)->getKey();
                    $summary['groups']++;
                }
            }

            foreach ($mode === TelegramDeliveryMode::Group ? [] : $contacts['phones'] as $phone) {
                $direct[] = self::record($notification, NotificationDeliveryChannel::WhatsApp, NotificationDeliveryStatus::Queued, $phone, null, null, $phone);
                $summary['whatsapp']++;
            }

            foreach ($contacts['emails'] as $email) {
                $direct[] = self::record($notification, NotificationDeliveryChannel::Mail, NotificationDeliveryStatus::Queued, $email, null, null, $email);
                $summary['mail']++;
            }
        });

        foreach ($telegramIds as $id) {
            self::queueTelegram((int) $id);
        }

        foreach ($direct as $delivery) {
            self::sendDirect($delivery, false);
        }

        if ($notification->withDatabaseNotification) {
            $summary['database'] = self::notifyPanelUsers($notification, $contacts['users'], $contacts['telegram']);
        }

        return $summary;
    }

    /**
     * Prueba directa a un solo destino desde CONFIGURACIÓN → Telegram. Pasa por
     * la misma bitácora, job y respaldo que cualquier aviso.
     */
    public static function sendTestTo(TelegramRecipient $recipient, string $actor): NotificationDelivery
    {
        $notification = new InternalNotification(
            SystemNotificationKey::TelegramTest,
            'Prueba de notificación a Telegram',
            'Hola, '.$recipient->name.'. Si lee este mensaje, este destino recibe bien las notificaciones internas.'."\n\n".'Enviado por '.$actor.' el '.now()->timezone(config('app.timezone'))->format('d/m/Y H:i').'.',
            false,
            $actor,
            false,
        );

        $delivery = DB::transaction(fn (): NotificationDelivery => self::record($notification, NotificationDeliveryChannel::Telegram, NotificationDeliveryStatus::Pending, $recipient->name, $recipient));

        self::queueTelegram($delivery->getKey());

        return $delivery;
    }

    /**
     * Encola el envío por Telegram en su cola propia. También lo usa el
     * reintento de pendientes viejos: el job es idempotente.
     */
    public static function queueTelegram(int $deliveryId): void
    {
        SendTelegramDeliveryJob::dispatch($deliveryId)
            ->onQueue((string) config('telegram.queue', 'notifications'))
            ->afterCommit();
    }

    /**
     * Envía por WhatsApp o correo un envío ya registrado (directo o de respaldo).
     * El cuerpo guardado ya es el mensaje completo con formato de WhatsApp.
     *
     * $sync = true para las alertas que no pueden depender de la cola (colas y
     * errores): salen en el acto, como antes de existir Telegram.
     */
    public static function sendDirect(NotificationDelivery $delivery, bool $isFallback, bool $sync = false): void
    {
        $queue = (string) config('telegram.queue', 'notifications');
        $context = [
            'source' => 'internal-notification',
            'notification_key' => $delivery->notification_key,
            'delivery_uuid' => $delivery->uuid,
        ];

        try {
            if ($delivery->channel === NotificationDeliveryChannel::WhatsApp && filled($delivery->attachment_url)) {
                $document = new SendNotificacionWhatsAppDocument(null, (string) $delivery->body, (string) $delivery->target, (string) $delivery->attachment_url, (string) ($delivery->attachment_name ?: 'documento.pdf'), $context);

                $sync ? dispatch_sync($document) : dispatch($document->onQueue($queue))->afterCommit();
            } elseif ($delivery->channel === NotificationDeliveryChannel::WhatsApp) {
                if ($sync) {
                    SendNotificacionWhatsApp::dispatchSync(null, (string) $delivery->body, (string) $delivery->target, null, $context);
                } else {
                    SendNotificacionWhatsApp::dispatch(null, (string) $delivery->body, (string) $delivery->target, null, $context)->onQueue($queue)->afterCommit();
                }
            } elseif ($delivery->channel === NotificationDeliveryChannel::Mail) {
                $mail = new InternalNotificationMail((string) $delivery->title, TelegramText::plain((string) $delivery->body), $isFallback, $delivery->attachment_path, $delivery->attachment_name);

                if ($sync) {
                    Mail::to((string) $delivery->target)->send($mail);
                } else {
                    Mail::to((string) $delivery->target)->queue($mail->onQueue($queue)->afterCommit());
                }
            }

            if ($sync) {
                $delivery->forceFill(['status' => NotificationDeliveryStatus::Sent, 'sent_at' => now()])->save();
            }
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => NotificationDeliveryStatus::Failed,
                'error' => mb_substr(($sync ? 'No se pudo enviar: ' : 'No se pudo encolar: ').$exception->getMessage(), 0, 1000),
            ])->save();

            Log::error('Notificación interna: no se pudo enviar por el canal de respaldo.', ['delivery' => $delivery->uuid, 'error' => $exception->getMessage()]);
        }
    }

    public static function record(
        InternalNotification $notification,
        NotificationDeliveryChannel $channel,
        NotificationDeliveryStatus $status,
        string $label,
        ?TelegramRecipient $recipient = null,
        ?string $error = null,
        ?string $target = null,
        ?NotificationDelivery $parent = null,
    ): NotificationDelivery {
        return NotificationDelivery::query()->create([
            'uuid' => (string) Str::uuid(),
            'parent_id' => $parent?->getKey(),
            'notification_key' => $notification->key->value,
            'channel' => $channel,
            'status' => $status,
            'telegram_recipient_id' => $recipient?->getKey(),
            'user_id' => $recipient?->user_id,
            'target' => $target ?? $recipient?->chat_id,
            'target_label' => mb_substr($label, 0, 255),
            'title' => mb_substr($notification->title, 0, 255),
            'body' => $notification->message(),
            'attachment_path' => $notification->attachment?->path,
            'attachment_name' => $notification->attachment?->name,
            'attachment_url' => $notification->attachment?->url,
            'is_critical' => $notification->critical,
            'error' => $error,
            'created_by' => $notification->actor,
        ]);
    }

    public static function telegramAvailable(): bool
    {
        return (bool) config('telegram.enabled', true) && TelegramBotClient::make()->isConfigured();
    }

    private static function effectiveMode(InternalNotification $notification, SystemNotificationRecipientSetting $setting): TelegramDeliveryMode
    {
        if (! $notification->key->supportsTelegram() || ! self::telegramAvailable()) {
            return TelegramDeliveryMode::Off;
        }

        $mode = $setting->telegramMode();

        if ($mode->includesGroups() && ! $notification->key->allowsTelegramGroups()) {
            return TelegramDeliveryMode::Person;
        }

        return $mode;
    }

    /**
     * @return list<TelegramRecipient>
     */
    private static function groups(SystemNotificationRecipientSetting $setting): array
    {
        $ids = $setting->telegramGroupIds();

        if ($ids === []) {
            return [];
        }

        return TelegramRecipient::query()->groups()->whereKey($ids)->get()->all();
    }

    /**
     * Campanita del panel para los usuarios del aviso: siempre, falle lo que falle.
     *
     * @param  Collection<int, User>  $users
     * @param  Collection<int, TelegramRecipient>  $telegram
     */
    private static function notifyPanelUsers(InternalNotification $notification, Collection $users, Collection $telegram): int
    {
        $targets = $users->keyBy('id');

        foreach ($telegram as $recipient) {
            if ($recipient->user instanceof User) {
                $targets->put($recipient->user->getKey(), $recipient->user);
            }
        }

        $sent = 0;

        foreach ($targets as $user) {
            if (strtoupper(trim((string) $user->status)) !== 'ACTIVO') {
                continue;
            }

            try {
                Notification::make()
                    ->title($notification->title)
                    ->body(Str::limit(TelegramText::plain($notification->message()), 400))
                    ->icon('heroicon-o-bell-alert')
                    ->color($notification->critical ? 'danger' : 'info')
                    ->sendToDatabase($user);
                $sent++;
            } catch (Throwable $exception) {
                Log::warning('Notificación interna: no se pudo dejar la campanita.', ['user_id' => $user->getKey(), 'error' => $exception->getMessage()]);
            }
        }

        return $sent;
    }
}
