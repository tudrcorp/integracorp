<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicineAllergyList extends Model
{
    protected $table = 'telemedicine_allergy_lists';

    protected $fillable = [
        'code',
        'category',
        'description',
    ];
}