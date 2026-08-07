<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpDeskEvent extends Model
{
    protected $table = 'help_desk_events';

    protected $fillable = [
        'help_desk_id',
        'user_id',
        'actor_name',
        'type',
        'body_html',
        'meta',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function helpDesk(): BelongsTo
    {
        return $this->belongsTo(HelpDesk::class, 'help_desk_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
