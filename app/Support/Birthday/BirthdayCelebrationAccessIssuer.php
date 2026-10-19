<?php

declare(strict_types=1);

namespace App\Support\Birthday;

use App\Enums\BirthdayCouponRedemptionStatus;
use App\Enums\BirthdayCouponType;
use App\Models\BirthdayCelebrationAccess;
use App\Models\BirthdayCouponToken;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BirthdayCelebrationAccessIssuer
{
    public const ACCESS_VALIDITY_HOURS = 24;

    public static function ensureForDelivery(
        int $deliveryId,
        string $fullName,
        ?string $phone = null,
        ?string $documentNumber = null,
        ?CarbonInterface $birthDate = null,
        ?CarbonInterface $celebrationDate = null,
    ): BirthdayCelebrationAccess {
        $existing = BirthdayCelebrationAccess::query()
            ->where('birthday_notification_delivery_id', $deliveryId)
            ->first();

        if ($existing !== null) {
            self::syncRecipientSnapshot($existing, $fullName, $phone, $documentNumber, $birthDate);

            return $existing->load('couponTokens');
        }

        $issuedAt = $celebrationDate ?? now();
        $expiresAt = $issuedAt->copy()->addHours(self::ACCESS_VALIDITY_HOURS);

        return DB::transaction(function () use ($deliveryId, $fullName, $phone, $documentNumber, $birthDate, $expiresAt): BirthdayCelebrationAccess {
            $access = BirthdayCelebrationAccess::query()->create([
                'birthday_notification_delivery_id' => $deliveryId,
                'public_token' => BirthdayCelebrationTokenGenerator::generate(),
                'full_name' => trim($fullName),
                'document_number' => filled($documentNumber) ? Str::upper(trim($documentNumber)) : null,
                'phone' => filled($phone) ? trim($phone) : null,
                'birth_date' => $birthDate?->toDateString(),
                'expires_at' => $expiresAt,
            ]);

            foreach (BirthdayCouponType::all() as $couponType) {
                BirthdayCouponToken::query()->create([
                    'birthday_celebration_access_id' => $access->id,
                    'coupon_type' => $couponType,
                    'public_token' => BirthdayCelebrationTokenGenerator::generate(),
                    'status' => BirthdayCouponRedemptionStatus::Pending,
                    'expires_at' => $expiresAt,
                ]);
            }

            return $access->load('couponTokens');
        });
    }

    public static function findAccessByPublicToken(string $token): ?BirthdayCelebrationAccess
    {
        $normalized = trim($token);
        if ($normalized === '') {
            return null;
        }

        return BirthdayCelebrationAccess::query()
            ->where('public_token', $normalized)
            ->with('couponTokens')
            ->first();
    }

    public static function findCouponByPublicToken(string $token): ?BirthdayCouponToken
    {
        $normalized = trim($token);
        if ($normalized === '') {
            return null;
        }

        return BirthdayCouponToken::query()
            ->where('public_token', $normalized)
            ->with('celebrationAccess')
            ->first();
    }

    private static function syncRecipientSnapshot(
        BirthdayCelebrationAccess $access,
        string $fullName,
        ?string $phone,
        ?string $documentNumber,
        ?CarbonInterface $birthDate,
    ): void {
        $access->forceFill(array_filter([
            'full_name' => trim($fullName),
            'phone' => filled($phone) ? trim($phone) : null,
            'document_number' => filled($documentNumber) ? Str::upper(trim($documentNumber)) : null,
            'birth_date' => $birthDate?->toDateString(),
        ], fn ($value) => $value !== null && $value !== ''))->save();
    }
}
