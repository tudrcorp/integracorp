<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataNotification extends Model
{
    protected $table = 'data_notifications';

    protected $fillable = [
        'mass_notification_id',
        'fullName',
        'email',
        'phone',
    ];

    public function massNotification()
    {
        return $this->belongsTo(MassNotification::class);
    }
}