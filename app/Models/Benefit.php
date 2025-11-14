<?php

namespace App\Models;

use COM;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Benefit extends Model
{
    protected $table = 'benefits';

    protected $fillable = [
        'plan_id',
        'limit_id',
        'code',
        'description',
        'status',
        'created_by',
        'price',
    ];

    public function limit(): BelongsTo
    {
        return $this->belongsTo(Limit::class, 'limit_id', 'id');
    }

    /**
     * Get all of the comments for the Benefit
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'id');
    }

}