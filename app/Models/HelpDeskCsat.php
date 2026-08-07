<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpDeskCsat extends Model
{
    protected $table = 'help_desk_csats';

    protected $fillable = [
        'help_desk_id',
        'user_id',
        'score',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
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
