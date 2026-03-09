<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationStatusService extends Model
{
    //
    protected $table = 'operation_status_services';

    protected $fillable = [
        'description',
        'status',
        'created_by',
        'updated_by',
    ];
}
