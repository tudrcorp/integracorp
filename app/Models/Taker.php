<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Taker extends Model
{
    protected $table = 'takers';

    protected $fillable = [
        'full_name',
        'type_document',
        'number_document',
        'status',
        'created_by',
    ];
}