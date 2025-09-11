<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BirthdayNotification extends Model
{
    protected $table = 'birthday_notifications';

    protected $fillable = [
        'image',
        'video',
        'link',
        'content',
        'data_type',
        'status',
    ];

    // protected $casts = [
    //     'data_type' => 'array',
    // ];

    // public function scopeActive($query)
    // {
    //     return $query->where('status', 'ACTIVA');
    // }

    // public function scopeInactive($query)
    // {
    //     return $query->where('status', 'INACTIVA');
    // }

}