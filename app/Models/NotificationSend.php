<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSend extends Model
{
    //
    protected $table = 'notification_sends';

    protected $fillable = [
        'type',
        'group',
        'success',
        'failed',
        'date_send'
    ];
}
