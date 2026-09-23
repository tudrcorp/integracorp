<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bloqueo de acceso de un usuario, decidido desde el monitor en vivo.
 * Nunca se borra: al levantarlo se registra quién, cuándo y por qué.
 */
class SecurityUserBlock extends Model
{
    protected $table = 'security_user_blocks';

    protected $fillable = [
        'user_id',
        'user_name',
        'user_email',
        'reason',
        'expires_at',
        'blocked_by_id',
        'blocked_by_name',
        'blocked_from_ip',
        'lifted_at',
        'lifted_by_id',
        'lifted_by_name',
        'lift_reason',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'lifted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<SecurityUserBlock>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('lifted_at')
            ->where(fn (Builder $inner): Builder => $inner->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
