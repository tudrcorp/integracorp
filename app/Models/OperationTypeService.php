<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationTypeService extends Model
{
    protected $table = 'operation_type_services';

    protected $fillable = [
        'description',
        'status',
        'created_by',
        'updated_by',
    ];
}
