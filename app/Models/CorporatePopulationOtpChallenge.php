<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Reto OTP para aplicar o revertir una actualización de población corporativa.
 */
class CorporatePopulationOtpChallenge extends Model
{
    public const PURPOSE_APPLY = 'APPLY';

    public const PURPOSE_ROLLBACK = 'ROLLBACK';

    protected $table = 'corporate_population_otp_challenges';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'user_id',
        'corporate_population_update_id',
        'purpose',
        'code_hash',
        'expires_at',
        'attempt_count',
        'max_attempts',
        'consumed_at',
        'last_sent_at',
        'emails_sent',
        'phones_sent',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'code_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'attempt_count' => 'integer',
            'max_attempts' => 'integer',
            'emails_sent' => 'integer',
            'phones_sent' => 'integer',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at instanceof Carbon && $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function hasAttemptsLeft(): bool
    {
        return $this->attempt_count < $this->max_attempts;
    }

    public function isActive(): bool
    {
        return ! $this->isConsumed() && ! $this->isExpired() && $this->hasAttemptsLeft();
    }

    public function secondsUntilResend(): int
    {
        if ($this->last_sent_at === null) {
            return 0;
        }

        $wait = (int) config('clinical-entitlements.otp.resend_wait_seconds', 120);

        return max(0, $wait - (int) $this->last_sent_at->diffInSeconds(now()));
    }
}
