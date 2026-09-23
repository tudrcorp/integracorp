<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * IP en la lista negra, decidida por un analista desde el monitor en vivo.
 * Nunca se borra: al levantarla se registra quién, cuándo y por qué, y
 * `evidence` conserva lo que el sistema veía de la IP al momento de bloquearla.
 */
class SecurityIpBlock extends Model
{
    protected $table = 'security_ip_blocks';

    protected $fillable = [
        'ip',
        'verdict',
        'reason',
        'evidence',
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
            'evidence' => 'array',
            'expires_at' => 'datetime',
            'lifted_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<SecurityIpBlock>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('lifted_at')
            ->where(fn (Builder $inner): Builder => $inner->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
