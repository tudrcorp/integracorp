<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'description',
        'response',
        'route',
        'method',
        'ip',
        'user_agent'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}