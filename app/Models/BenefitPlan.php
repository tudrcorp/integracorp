<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class BenefitPlan extends Pivot
{
    protected $table = 'benefit_plans';

    protected $fillable = [
        'plan_id',
        'benefit_id',
        'description',
        'created_by',
    ];

    public static function booted(): void
    {
        static::creating(function ($record) {
            $record->description = Benefit::where('id', $record->benefit_id)->first()->description;
        });
    }
}
