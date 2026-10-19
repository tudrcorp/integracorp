<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BirthdayNotificationExclusion extends Model
{
    protected $table = 'birthday_notification_exclusions';

    protected $fillable = [
        'birthday_notification_id',
        'data_type',
        'recipient_id',
    ];

    /**
     * @return BelongsTo<BirthdayNotification, $this>
     */
    public function birthdayNotification(): BelongsTo
    {
        return $this->belongsTo(BirthdayNotification::class, 'birthday_notification_id');
    }
}
