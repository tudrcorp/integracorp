<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DownloadZoneLike extends Model
{
    /** @use HasFactory<\Database\Factories\DownloadZoneLikeFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'download_zone_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function downloadZone(): BelongsTo
    {
        return $this->belongsTo(DownloadZone::class);
    }
}
