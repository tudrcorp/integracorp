<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicineExamenList extends Model
{
    protected $table = 'telemedicine_examen_lists';

    protected $fillable = [
        'code',
        'category',
        'description',
    ];
}