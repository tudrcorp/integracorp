<?php

namespace App\Models;

use App\Support\BirthdayNotificationRecipientDelivery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BirthdayNotification extends Model
{
    protected $table = 'birthday_notifications';

    protected $fillable = [
        'title',
        'content',
        'file',
        'is_sent',
        'is_approved',
        'approved_by',
        'reason',
        'header_title',
        'channels',
        'data_type',
        'type',
    ];

    protected $casts = [
        'channels' => 'array',
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(BirthdayNotificationDelivery::class, 'birthday_notification_id');
    }

    /**
     * @return array{
     *     email: array{sent: int, failed: int, pending: int, skipped: int},
     *     whatsapp: array{sent: int, failed: int, pending: int, skipped: int}
     * }
     */
    public function deliveryStats(?CarbonInterface $deliveryDate = null): array
    {
        return BirthdayNotificationRecipientDelivery::summarizeForNotification($this->id, $deliveryDate);
    }
}
