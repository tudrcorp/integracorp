<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reunión declarada por un colaborador: puede haber varias por día, nunca dos
 * abiertas a la vez. `ended_at` NULL = sigue en reunión. `end_reason`: `manual`
 * (pulsó «Terminé»), `prompt` (respondió que terminó), `lunch` (salió a
 * almorzar) o `auto` (no marcó el fin y se cerró sola).
 */
class UserWorkMeeting extends Model
{
    /** @use HasFactory<\Database\Factories\UserWorkMeetingFactory> */
    use HasFactory;

    public const END_MANUAL = 'manual';

    public const END_PROMPT = 'prompt';

    public const END_LUNCH = 'lunch';

    public const END_AUTO = 'auto';

    protected $fillable = [
        'user_id',
        'work_date',
        'started_at',
        'ended_at',
        'end_reason',
        'reason',
        'started_from',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
