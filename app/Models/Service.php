<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $table = 'services';

    protected $fillable = [
        'code',
        'definition',
        'status',
        'created_by',
    ];

    public function specificServices()
    {
        return $this->hasMany(SpecificService::class, 'service_id', 'id');
    }

    
}