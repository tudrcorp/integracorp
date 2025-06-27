<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgencyType extends Model
{

    protected $table = 'agency_types';

    protected $fillable = [
        'code',
        'definition',
        'status',
        'created_by'
    ];

    
    
}