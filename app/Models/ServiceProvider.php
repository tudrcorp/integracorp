<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceProvider extends Model
{
    protected $table = 'service_providers';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
    ];
}