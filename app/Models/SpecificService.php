<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpecificService extends Model
{
    protected $table = 'specific_services';

    protected $fillable = [
        'code',
        'service_id',
        'definition',
        'status',
        'created_by',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id', 'id');
    }

}