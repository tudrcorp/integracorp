<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    protected $table = 'rols';

    protected $fillable = [
        'name',
        'description',
        'created_by',
        'updated_by',
    ];
}
