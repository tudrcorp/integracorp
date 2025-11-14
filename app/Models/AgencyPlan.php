<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class AgencyPlan extends Pivot
{
    protected $table = 'agency_plans';

    public static function booted(): void
    {
        static::creating(function ($record) {
            $record->description =  Plan::where('id', $record->plan_id)->first()->description;
        });
    }
}