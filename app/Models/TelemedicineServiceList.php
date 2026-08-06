<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicineServiceList extends Model
{
    public const CONSULTA_GENERAL_ID = 17;

    protected $table = 'telemedicine_service_lists';

    protected $fillable = ['name', 'description'];
}
