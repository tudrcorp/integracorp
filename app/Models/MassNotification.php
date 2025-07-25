<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MassNotification extends Model
{
    protected $table = 'mass_notifications';
    
    protected $fillable = [
        'title',
        'content',
        'image',
        'name',
        'email',
        'phone',
        'link',
        'is_sent',
        'is_approved',
        'approved_by',
        'reason'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}