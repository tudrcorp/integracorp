<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UpgradeBenefit extends Model
{
    protected $table = 'upgrade_benefits';

    public $timestamps = false;

    protected $fillable = [
        'code',
        'description',
        'price',
        'status',
    ];
}