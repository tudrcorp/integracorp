<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicineListLaboratory extends Model
{
    protected $table = 'telemedicine_list_laboratories';

    protected $fillable = [
        'name',
        'type',
    ];
}