<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MassNotification extends Model
{
    protected $table = 'mass_notifications';
    
    protected $fillable = [
        'title',
        'content',
        'file',
        'name',
        'email',
        'phone',
        'is_sent',
        'is_approved',
        'approved_by',
        'reason',
        'date_programed',
        'header_title',
        'channels',
    ];

    protected $casts = [
        'channels' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function dataNotifications()
    {
        return $this->hasMany(DataNotification::class);
    }
}