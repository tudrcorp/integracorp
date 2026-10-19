<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BirthdayCelebrationAccess extends Model
{
    protected $fillable = [
        'birthday_notification_delivery_id',
        'public_token',
        'full_name',
        'document_number',
        'phone',
        'birth_date',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'expires_at' => 'datetime',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(BirthdayNotificationDelivery::class, 'birthday_notification_delivery_id');
    }

    public function couponTokens(): HasMany
    {
        return $this->hasMany(BirthdayCouponToken::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
