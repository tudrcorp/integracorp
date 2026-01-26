<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationFailed extends Model
{
    //
    protected $table = 'notification_faileds';
    protected $fillable = [
        'type',
        'name',
        'email',
        'phone',
        'message',
        'group',
    ];
}
