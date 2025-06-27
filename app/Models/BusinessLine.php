<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessLine extends Model
{
    protected $table = 'business_lines';

    protected $fillable = [
        'business_unit_id',
        'code',
        'definition',
        'status',
        'created_by',
    ];


    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class, 'business_unit_id', 'id');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class, 'business_line_id', 'id');
    }

}