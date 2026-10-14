<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\CrmInbox\CrmInboxPulse;
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
        'attempts',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => CrmInboxPulse::bump());
        static::deleted(fn () => CrmInboxPulse::bump());
    }

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'meta' => 'array',
            'attempts' => 'integer',
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
