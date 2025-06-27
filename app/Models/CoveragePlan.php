<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CoveragePlan extends Pivot
{
    protected $table = 'coverage_plans';

    public static function booted(): void
    {
        static::creating(function ($record) {
            $record->price =  Coverage::where('id', $record->coverage_id)->first()->price;
        });
    }
}