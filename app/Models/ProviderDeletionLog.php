<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una eliminación de proveedor y, si se deshizo, su restauración.
 * Ver {@see \App\Support\Operations\ProviderDeletionRestoration}.
 */
class ProviderDeletionLog extends Model
{
    protected $fillable = [
        'provider_type',
        'provider_id',
        'provider_name',
        'reason',
        'deleted_by_user_id',
        'deleted_by_name',
        'restored_at',
        'restored_by_user_id',
        'restored_by_name',
        'restoration_reason',
        'restoration_summary',
    ];

    protected function casts(): array
    {
        return [
            'restored_at' => 'datetime',
            'restoration_summary' => 'array',
        ];
    }

    /**
     * @return HasMany<ProviderDeletionLogItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProviderDeletionLogItem::class)->orderBy('id');
    }
}
