<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicineStudiesList extends Model
{
    protected $table = 'telemedicine_studies_lists';

    protected $fillable = [
        'code',
        'category',
        'description',
    ];
}