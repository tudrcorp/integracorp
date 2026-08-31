<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClinicalServiceChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ClinicalServiceOverrideChallenge extends Model
{
    protected $table = 'clinical_service_override_challenges';

    protected $fillable = [
        'public_id',
        'user_id',
        'telemedicine_patient_id',
        'telemedicine_case_id',
        'plan_id',
        'benefit_id',
        'channel',
        'telemedicine_service_list_id',
        'reason',
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => ClinicalServiceChannel::class,
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'attempt_count' => 'integer',
            'max_attempts' => 'integer',
            'emails_sent' => 'integer',
            'phones_sent' => 'integer',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected $hidden = [
        'code_hash',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(TelemedicinePatient::class, 'telemedicine_patient_id');
    }

    public function benefit(): BelongsTo
    {
        return $this->belongsTo(Benefit::class);
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
        $elapsed = (int) $this->last_sent_at->diffInSeconds(now());

        return max(0, $wait - $elapsed);
    }
}
