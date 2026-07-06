<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanGeneratorRow extends Model
{
    protected $fillable = [
        'plan_generator_id',
        'benefit_label',
        'sort_order',
    ];

    public function planGenerator(): BelongsTo
    {
        return $this->belongsTo(PlanGenerator::class);
    }

    public function cells(): HasMany
    {
        return $this->hasMany(PlanGeneratorCell::class, 'plan_generator_row_id');
    }
}
