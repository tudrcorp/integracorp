<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DownloadZone extends Model
{
    protected $table = 'download_zones';

    protected $fillable = [
        'zone_id',
        'position',
        'document',
        'status',
        'image_icon',
        'description',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(DownloadZoneLike::class);
    }
}
