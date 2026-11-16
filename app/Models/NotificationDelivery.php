<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationDeliveryChannel;
use App\Enums\NotificationDeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bitácora de una notificación interna por un canal. Se escribe antes de enviar.
 *
 * @property NotificationDeliveryChannel $channel
 * @property NotificationDeliveryStatus $status
 */
class NotificationDelivery extends Model
{
    protected $fillable = [
        'uuid',
        'parent_id',
        'notification_key',
        'channel',
        'status',
        'telegram_recipient_id',
        'user_id',
        'target',
        'target_label',
        'title',
        'body',
        'attachment_path',
        'attachment_name',
        'attachment_url',
        'is_critical',
        'attempts',
        'provider_message_id',
        'error',
        'sent_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => NotificationDeliveryChannel::class,
            'status' => NotificationDeliveryStatus::class,
            'is_critical' => 'boolean',
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function fallbacks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(TelegramRecipient::class, 'telegram_recipient_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasAttachment(): bool
    {
        return filled($this->attachment_path) || filled($this->attachment_url);
    }
}
