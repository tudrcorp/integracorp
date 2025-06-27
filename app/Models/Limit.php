<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Limit extends Model
{
    protected $table = 'limits';

    protected $fillable = [
        'code',
        'description',
        'status',
        'created_by',
    ];

    /**
     * Get the user that owns the Limit
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function benefits(): HasMany
    {
        return $this->hasMany(Benefit::class, 'limit_id', 'id');
    }

}