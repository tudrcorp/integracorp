<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpDeskNoteRead extends Model
{
    protected $table = 'help_desk_note_reads';

    protected $fillable = [
        'help_desk_id',
        'user_id',
        'last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
        ];
    }

    public function helpDesk(): BelongsTo
    {
        return $this->belongsTo(HelpDesk::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
