<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resumen de uso del sistema de un usuario en un día. Lo escribe solo el job de
 * volcado (`FlushUserActivityJob`) a partir de los minutos marcados en vivo.
 */
class UserActivityDay extends Model
{
    /** @use HasFactory<\Database\Factories\UserActivityDayFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'activity_date',
        'first_minute',
        'last_minute',
        'online_minutes',
        'active_minutes',
        'idle_minutes',
        'background_minutes',
        'page_views',
        'actions',
        'downloads',
        'hourly_active',
        'minute_states',
        'workday_kind',
        'work_base_minutes',
        'work_active_minutes',
        'work_idle_minutes',
        'work_absent_minutes',
        'lunch_minutes',
        'lunch_excess_minutes',
        'overtime_minutes',
        'guard_minutes',
        'guard_present_minutes',
        'guard_active_minutes',
        'meeting_minutes',
        'work_meeting_minutes',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'first_minute' => 'integer',
            'last_minute' => 'integer',
            'online_minutes' => 'integer',
            'active_minutes' => 'integer',
            'idle_minutes' => 'integer',
            'background_minutes' => 'integer',
            'page_views' => 'integer',
            'actions' => 'integer',
            'downloads' => 'integer',
            'hourly_active' => 'array',
            'work_base_minutes' => 'integer',
            'work_active_minutes' => 'integer',
            'work_idle_minutes' => 'integer',
            'work_absent_minutes' => 'integer',
            'lunch_minutes' => 'integer',
            'lunch_excess_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'guard_minutes' => 'integer',
            'guard_present_minutes' => 'integer',
            'guard_active_minutes' => 'integer',
            'meeting_minutes' => 'integer',
            'work_meeting_minutes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
