<?php

namespace App\Models;

use App\Enums\MassNotificationDeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BirthdayNotificationDelivery extends Model
{
    protected $table = 'birthday_notification_deliveries';

    protected $fillable = [
        'birthday_notification_id',
        'full_name',
        'email',
        'phone',
        'delivery_date',
        'email_status',
        'email_sent_at',
        'email_error',
        'whatsapp_status',
        'whatsapp_sent_at',
        'whatsapp_error',
    ];

    protected function casts(): array
    {
        return [
            'delivery_date' => 'date',
            'email_status' => MassNotificationDeliveryStatus::class,
            'whatsapp_status' => MassNotificationDeliveryStatus::class,
            'email_sent_at' => 'datetime',
            'whatsapp_sent_at' => 'datetime',
        ];
    }

    public function birthdayNotification(): BelongsTo
    {
        return $this->belongsTo(BirthdayNotification::class, 'birthday_notification_id');
    }
}
