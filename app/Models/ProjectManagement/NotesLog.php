<?php

declare(strict_types=1);

namespace App\Models\ProjectManagement;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotesLog extends Model
{
    protected $table = 'notes_logs';

    protected $fillable = [
        'user_id',
        'content',
        'notable_type',
        'notable_id',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function notable(): MorphTo
    {
        return $this->morphTo();
    }
}
