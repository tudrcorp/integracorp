<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Almuerzo declarado por un colaborador: uno por día. `ended_at` NULL = sigue
 * almorzando. `end_reason`: `manual` (pulsó «Volver»), `prompt` (respondió que
 * volvió) o `auto` (no lo marcó y se cerró al terminar la jornada).
 */
class UserWorkBreak extends Model
{
    /** @use HasFactory<\Database\Factories\UserWorkBreakFactory> */
    use HasFactory;

    public const END_MANUAL = 'manual';

    public const END_PROMPT = 'prompt';

    public const END_AUTO = 'auto';

    protected $fillable = [
        'user_id',
        'work_date',
        'started_at',
        'ended_at',
        'end_reason',
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
