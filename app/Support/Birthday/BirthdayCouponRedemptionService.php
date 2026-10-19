<?php

declare(strict_types=1);

namespace App\Support\Birthday;

use App\Enums\BirthdayCouponRedemptionStatus;
use App\Models\BirthdayCouponToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BirthdayCouponRedemptionService
{
    public function syncExpiredStatus(BirthdayCouponToken $token): BirthdayCouponToken
    {
        if ($token->status === BirthdayCouponRedemptionStatus::Pending && $token->isExpired()) {
            $token->forceFill(['status' => BirthdayCouponRedemptionStatus::Expired])->save();
        }

        return $token->refresh();
    }

    public function redeem(BirthdayCouponToken $token, User $user, ?string $ipAddress = null): BirthdayCouponToken
    {
        return DB::transaction(function () use ($token, $user, $ipAddress): BirthdayCouponToken {
            /** @var BirthdayCouponToken|null $locked */
            $locked = BirthdayCouponToken::query()
                ->whereKey($token->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw ValidationException::withMessages([
                    'coupon' => 'No encontramos este cupón.',
                ]);
            }

            $this->syncExpiredStatus($locked);

            if ($locked->status === BirthdayCouponRedemptionStatus::Used) {
                throw ValidationException::withMessages([
                    'coupon' => 'Este cupón ya fue marcado como usado.',
                ]);
            }

            if ($locked->status === BirthdayCouponRedemptionStatus::Expired || $locked->isExpired()) {
                throw ValidationException::withMessages([
                    'coupon' => 'Este cupón está expirado.',
                ]);
            }

            $locked->forceFill([
                'status' => BirthdayCouponRedemptionStatus::Used,
                'used_at' => now(),
                'used_by_user_id' => $user->id,
                'used_from_ip' => $ipAddress,
            ])->save();

            return $locked->refresh()->load('celebrationAccess', 'usedByUser');
        });
    }
}
