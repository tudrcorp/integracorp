<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BirthdayCouponRedemptionStatus;
use App\Enums\BirthdayCouponType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BirthdayCouponToken extends Model
{
    protected $fillable = [
        'birthday_celebration_access_id',
        'coupon_type',
        'public_token',
        'status',
        'expires_at',
        'used_at',
        'used_by_user_id',
        'used_from_ip',
    ];

    protected function casts(): array
    {
        return [
            'coupon_type' => BirthdayCouponType::class,
            'status' => BirthdayCouponRedemptionStatus::class,
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function celebrationAccess(): BelongsTo
    {
        return $this->belongsTo(BirthdayCelebrationAccess::class, 'birthday_celebration_access_id');
    }

    public function usedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by_user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isRedeemable(): bool
    {
        return $this->status === BirthdayCouponRedemptionStatus::Pending && ! $this->isExpired();
    }
}
