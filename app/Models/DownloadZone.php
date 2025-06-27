<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DownloadZone extends Model
{
    protected $table = 'download_zones';

    protected $fillable = [
        'zone_id',
        'document',
        'status',
        'image_icon',
        'description',
    ];

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }
}