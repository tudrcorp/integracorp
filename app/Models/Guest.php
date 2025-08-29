<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    protected $table = 'guests';

    protected $fillable = [
        'event_id',
        'fullName',
        'phone',
        'agency',
        'companion',
        'webBrowser',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
    
}