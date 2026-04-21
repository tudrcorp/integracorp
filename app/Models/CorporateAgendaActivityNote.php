<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorporateAgendaActivityNote extends Model
{
    protected $fillable = [
        'activity_id',
        'user_id',
        'note',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(CorporateAgendaActivity::class, 'activity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
