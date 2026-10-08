<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmHandoffMessage extends Model
{
    protected $fillable = [
        'message_id',
        'handoff_id',
        'phone',
        'body',
        'received_at',
        'direction',
        'kind',
        'status',
        'user_id',
        'provider_message_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<CrmHandoffEnvelope, $this>
     */
    public function envelope(): BelongsTo
    {
        return $this->belongsTo(CrmHandoffEnvelope::class, 'handoff_id', 'handoff_id');
    }
}
