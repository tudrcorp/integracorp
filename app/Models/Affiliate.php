<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Affiliate extends Model
{
    protected $table = 'affiliates';

    protected $fillable = [
        'affiliation_id',
        'full_name',
        'birth_date',
        'nro_identificacion',
        'sex',
        'age',
        'relationship',
        'document',
    ];

    public function affiliation()
    {
        return $this->belongsTo(Affiliation::class);
    }
}