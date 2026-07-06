<?php

namespace App\Models;

use App\Enums\MassNotificationDeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataNotification extends Model
{
    protected $table = 'data_notifications';

    protected $fillable = [
        'mass_notification_id',
        'fullName',
        'email',
        'phone',
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
            'email_status' => MassNotificationDeliveryStatus::class,
            'whatsapp_status' => MassNotificationDeliveryStatus::class,
            'email_sent_at' => 'datetime',
            'whatsapp_sent_at' => 'datetime',
        ];
    }

    public function massNotification(): BelongsTo
    {
        return $this->belongsTo(MassNotification::class, 'mass_notification_id', 'id');
    }
}
