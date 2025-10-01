<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicinePriority extends Model
{
    protected $table = 'telemedicine_priorities';

    protected $fillable = [
        'name',
    ];
}