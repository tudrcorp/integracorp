<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BirthdayNotification extends Model
{
    protected $table = 'birthday_notifications';

    protected $fillable = [
        'title',
        'content',
        'file',
        'is_sent',
        'is_approved',
        'approved_by',
        'reason',
        'header_title',
        'channels',
        'data_type',
        'type'
    ];

    protected $casts = [
        'channels' => 'array',
    ];

    // public function scopeActive($query)
    // {
    //     return $query->where('status', 'ACTIVA');
    // }

    // public function scopeInactive($query)
    // {
    //     return $query->where('status', 'INACTIVA');
    // }

}