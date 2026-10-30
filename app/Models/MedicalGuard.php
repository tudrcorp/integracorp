<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\UserActivity\UserActivityClock;
use App\Support\UserActivity\Workday\WorkdayConfig;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Guardia de un médico de TDG en una sede. Se escribe solo por
 * {@see \App\Support\Operations\MedicalGuards\MedicalGuardScheduler}.
 */
class MedicalGuard extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'medical_guard_site_id',
        'telemedicine_doctor_id',
        'guard_date',
        'starts_at',
        'ends_at',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
        'deleted_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'guard_date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(static fn (MedicalGuard $guard) => self::audit('created', $guard));

        static::updated(static function (MedicalGuard $guard): void {
            $changes = collect($guard->getChanges())->except(['updated_at', 'updated_by_user_id'])->all();

            if ($changes !== []) {
                self::audit('updated', $guard, ['attribute_changes' => $changes]);
            }
        });

        static::deleted(static fn (MedicalGuard $guard) => self::audit('deleted', $guard));
    }

    /**
     * Siempre `Y-m-d`: los filtros por rango comparan esta columna directamente.
     */
    public function setGuardDateAttribute(mixed $value): void
    {
        $this->attributes['guard_date'] = CarbonImmutable::parse($value)->toDateString();
    }

    /**
     * @return BelongsTo<MedicalGuardSite, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(MedicalGuardSite::class, 'medical_guard_site_id');
    }

    /**
     * @return BelongsTo<TelemedicineDoctor, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(TelemedicineDoctor::class, 'telemedicine_doctor_id');
    }

    public function startMinute(): int
    {
        return (int) WorkdayConfig::parse($this->starts_at->format('H:i'));
    }

    public function durationMinutes(): int
    {
        return (int) $this->starts_at->diffInMinutes($this->ends_at);
    }

    /**
     * «7:00 a. m. – 7:00 p. m.», con la marca si termina al día siguiente.
     */
    public function scheduleLabel(): string
    {
        $endsNextDay = $this->ends_at->toDateString() !== $this->starts_at->toDateString();

        return UserActivityClock::formatMinute($this->startMinute())
            .' – '.UserActivityClock::formatMinute((int) WorkdayConfig::parse($this->ends_at->format('H:i')))
            .($endsNextDay ? ' (+1 día)' : '');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private static function audit(string $event, self $guard, array $extra = []): void
    {
        $user = Auth::user();

        Log::info('OPERACIONES: Guardia médica — '.$event.'.', [
            'audit' => 'medical_guard',
            'event' => $event,
            'record_id' => $guard->id,
            'site_id' => $guard->medical_guard_site_id,
            'telemedicine_doctor_id' => $guard->telemedicine_doctor_id,
            'starts_at' => $guard->starts_at?->toDateTimeString(),
            'ends_at' => $guard->ends_at?->toDateTimeString(),
            'actor_user_id' => $user?->id,
            'actor_name' => $user?->name,
            'request_ip' => request()?->ip(),
            ...$extra,
        ]);
    }
}
