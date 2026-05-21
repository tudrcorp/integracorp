<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CorporateAgendaSocialPlatform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorporateAgendaSocialPublication extends Model
{
    protected $fillable = [
        'creator_user_id',
        'publication_date',
        'platform',
        'brief',
        'attachments',
    ];

    protected function casts(): array
    {
        return [
            'publication_date' => 'date',
            'platform' => CorporateAgendaSocialPlatform::class,
            'attachments' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }
}
