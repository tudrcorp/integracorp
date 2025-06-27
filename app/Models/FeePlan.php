<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class FeePlan extends Pivot
{
    protected $table = 'fee_plans';

    public static function booted(): void
    {
        static::creating(function ($record) {
            $fee = Fee::where('id', $record->fee_id)->first();
            $record->range =  AgeRange::find($fee->age_range_id)->range;
            $record->price =  $fee->price;
        });
    }
}