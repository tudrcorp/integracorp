<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Limit extends Model
{
    protected $table = 'limits';

    protected $fillable = [
        'code',
        'description',
        'status',
        'created_by',
        'cuota',
    ];

    /**
     * Get the user that owns the Limit
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function benefits(): HasMany
    {
        return $this->hasMany(Benefit::class);
    }
}
