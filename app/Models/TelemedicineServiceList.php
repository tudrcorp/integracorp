<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicineServiceList extends Model
{
    protected $table = 'telemedicine_service_lists';

    protected $fillable = ['name', 'description'];
}