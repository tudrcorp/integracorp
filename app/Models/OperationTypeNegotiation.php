<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationTypeNegotiation extends Model
{
    protected $table = 'operation_type_negotiations';

    protected $fillable = [
        'description',
        'status',
        'created_by',
        'updated_by',
    ];
}
