<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationAudience;
use App\Enums\OutboundChannel;
use App\Enums\OutboundMessageStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora del Centro de notificaciones: un mensaje que salió (o se bloqueó) por
 * WhatsApp, correo o Telegram. Se conserva RETENTION_DAYS días.
 *
 * @property OutboundChannel $channel
 * @property OutboundMessageStatus $status
 * @property NotificationAudience $audience
 */
class OutboundMessage extends Model
{
    use MassPrunable;

    public const RETENTION_DAYS = 90;

    protected $fillable = [
        'channel',
        'status',
        'audience',
        'catalog_key',
        'message_type',
        'recipient',
        'recipient_key',
        'recipient_name',
        'recipients_count',
        'subject',
        'body',
        'attachment_name',
        'error',
        'origin',
        'is_critical',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => OutboundChannel::class,
            'status' => OutboundMessageStatus::class,
            'audience' => NotificationAudience::class,
            'recipients_count' => 'integer',
            'is_critical' => 'boolean',
        ];
    }

    public function catalogEntry(): BelongsTo
    {
        return $this->belongsTo(NotificationCatalogEntry::class, 'catalog_key', 'key');
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    /**
     * Teléfono o correo enmascarado para la pantalla: 0414•••4567, ga•••@dominio.com.
     */
    public function maskedRecipient(): string
    {
        $recipient = trim((string) $this->recipient);

        if ($recipient === '') {
            return '—';
        }

        if (str_contains($recipient, '@')) {
            [$user, $domain] = explode('@', $recipient, 2);

            return mb_substr($user, 0, 2).'•••@'.$domain;
        }

        $digits = (string) preg_replace('/\D+/', '', $recipient);

        if (strlen($digits) < 7) {
            return $recipient;
        }

        return substr($digits, 0, 4).'•••'.substr($digits, -4);
    }
}
