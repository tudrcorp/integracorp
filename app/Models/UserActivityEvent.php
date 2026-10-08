<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un paso del recorrido de un usuario: abrió una página, hizo una acción,
 * descargó algo, cambió de pestaña, quedó inactivo o volvió a trabajar.
 */
class UserActivityEvent extends Model
{
    /** @use HasFactory<\Database\Factories\UserActivityEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'event_key',
        'user_id',
        'occurred_at',
        'type',
        'label',
        'panel',
        'page',
        'path',
        'duration_ms',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'duration_ms' => 'integer',
            'status' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
