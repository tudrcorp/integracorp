<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coverage extends Model
{
    protected $table = 'coverages';

    protected $fillable = [
        'code',
        'price',
        'plan_id',
        'status',
        'created_by',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'id');
    }

}