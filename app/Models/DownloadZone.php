<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function zone(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
