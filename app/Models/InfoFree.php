<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InfoFree extends Model
{
    protected $table = 'info_frees';
    
    protected $fillable = [
        'fullName',
        'email',
        'phone',
        'sex',
        'address',
        'city',
        'country',
        'state',
        'region',
    ];
}