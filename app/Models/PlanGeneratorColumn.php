<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanGeneratorColumn extends Model
{
    protected $fillable = [
        'plan_generator_id',
        'column_key',
        'header_label',
        'sort_order',
    ];

    public function planGenerator(): BelongsTo
    {
        return $this->belongsTo(PlanGenerator::class);
    }
}
