<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ObservationCase extends Model
{
    protected $table = 'observation_cases';

    protected $fillable = [
        'telemedicine_case_id',
        'description',
        'created_by',
    ];

    public function telemedicineCase()
    {
        return $this->belongsTo(
            TelemedicineCase::class,
            'telemedicine_case_id',
            'id'
        );
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}