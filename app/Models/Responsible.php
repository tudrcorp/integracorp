<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Responsible extends Model
{
    protected $table = 'responsibles';

    protected $fillable = [
        'code',
        'full_name',
        'status',
        'created_by',
    ];
}