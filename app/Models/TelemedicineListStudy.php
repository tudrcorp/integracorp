<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicineListStudy extends Model
{
    protected $table = 'telemedicine_list_studies';

    protected $fillable = [
        'name',
        'type',
    ];
}