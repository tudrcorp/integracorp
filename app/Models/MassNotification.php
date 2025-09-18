<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'type',
    ];

    protected $casts = [
        'channels' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function dataNotifications(): HasMany
    {
        return $this->hasMany(DataNotification::class, 'mass_notification_id', 'id');
    }
}