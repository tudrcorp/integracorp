<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    protected $table = 'zones';

    protected $fillable = [
        'code',
        'zone',
        'status',
    ];

    public function downloadzones()
    {
        return $this->hasMany(DownloadZone::class);
    }
}