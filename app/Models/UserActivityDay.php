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
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
