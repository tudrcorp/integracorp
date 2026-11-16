<?php

declare(strict_types=1);

namespace App\Support\Telegram;

use App\Enums\NotificationDeliveryChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Jobs\SendNotificacionWhatsApp;
use App\Mail\InternalNotificationMail;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Envía a la persona su propio enlace y código de vinculación, por WhatsApp
 * (al teléfono de su usuario) o por correo, para que no tenga que escanear el
 * QR: tocar el enlace en el teléfono abre Telegram directo en el bot.
 *
 * Solo al teléfono y al correo registrados en su usuario, nunca a uno escrito
 * en el momento. Máximo 3 envíos cada 15 minutos por persona.
 */
final class TelegramLinkDelivery
{
    public const NOTIFICATION_KEY = 'telegram_link';

    public const MAX_SENDS = 3;

    public const WINDOW_SECONDS = 900;

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_MAIL = 'mail';

    /**
     * @return string destino enmascarado al que se envió (para mostrarlo)
     */
    public static function send(User $user, string $channel, string $url, string $code, Carbon $expiresAt): string
    {
        $target = match ($channel) {
            self::CHANNEL_WHATSAPP => trim((string) $user->phone),
            self::CHANNEL_MAIL => mb_strtolower(trim((string) $user->email)),
            default => throw new InvalidArgumentException('Canal desconocido.'),
        };

        if ($target === '') {
            throw new InvalidArgumentException($channel === self::CHANNEL_WHATSAPP
                ? 'Su usuario no tiene teléfono registrado. Use «Enviarme el enlace por correo» o pida a Sistemas que cargue su teléfono.'
                : 'Su usuario no tiene correo registrado.');
        }

        $limiterKey = 'telegram-link-delivery:'.$user->getKey();

        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_SENDS)) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($limiterKey) / 60));

            throw new InvalidArgumentException('Ya se le enviaron '.self::MAX_SENDS.' enlaces. Espere '.$minutes.' '.($minutes === 1 ? 'minuto' : 'minutos').' o use el código que ve en pantalla.');
        }

        RateLimiter::hit($limiterKey, self::WINDOW_SECONDS);

        $title = 'Vincular Telegram con IntegraCorp';
        $body = self::body($url, $code, $expiresAt);

        $delivery = NotificationDelivery::query()->create([
            'uuid' => (string) Str::uuid(),
            'notification_key' => self::NOTIFICATION_KEY,
            'channel' => $channel === self::CHANNEL_WHATSAPP ? NotificationDeliveryChannel::WhatsApp : NotificationDeliveryChannel::Mail,
            'status' => NotificationDeliveryStatus::Queued,
            'user_id' => $user->getKey(),
            'target' => $target,
            'target_label' => (string) $user->name,
            'title' => $title,
            'body' => 'Enlace y código de vinculación (no se guardan en la bitácora).',
            'created_by' => (string) $user->name,
        ]);

        if ($channel === self::CHANNEL_WHATSAPP) {
            SendNotificacionWhatsApp::dispatch(null, TelegramText::whatsApp($title, $body), $target, null, [
                'source' => 'telegram-link',
                'delivery_uuid' => $delivery->uuid,
            ])->onQueue((string) config('telegram.queue', 'notifications'))->afterCommit();
        } else {
            Mail::to($target)->queue((new InternalNotificationMail($title, $body, false))->onQueue((string) config('telegram.queue', 'notifications'))->afterCommit());
        }

        return self::mask($channel, $target);
    }

    public static function body(string $url, string $code, Carbon $expiresAt): string
    {
        $bot = (string) config('telegram.bot_username');

        return "Toque este enlace desde su teléfono y pulse «Iniciar» en Telegram:\n".$url
            ."\n\nO abra Telegram, busque @".$bot.' y escríbale este código: '.TelegramLinkService::formatShortCode($code)
            ."\n\nVence a las ".$expiresAt->timezone(config('app.timezone'))->format('h:i A').' y sirve una sola vez. Si usted no lo pidió, ignore este mensaje.';
    }

    public static function mask(string $channel, string $target): string
    {
        if ($channel === self::CHANNEL_MAIL) {
            [$local, $domain] = array_pad(explode('@', $target, 2), 2, '');

            return mb_substr($local, 0, 2).'•••@'.$domain;
        }

        $digits = (string) preg_replace('/\D+/', '', $target);

        return strlen($digits) >= 4 ? substr($digits, 0, 4).'•••'.substr($digits, -4) : '•••';
    }
}
