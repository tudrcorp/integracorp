<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AfilliationCorporatePlan extends Model
{
    protected $table = 'afilliation_corporate_plans';

    protected $fillable = [
        'affiliation_corporate_id',
        'code_affiliation',
        'plan_id',
        'coverage_id',
        'age_range_id',
        'fee',
        'subtotal_anual',
        'subtotal_quarterly',
        'subtotal_biannual',
        'subtotal_monthly',
        'status',
        'created_by',
        'total_persons'
    ];

    public function AffiliationCorporate()
    {
        return $this->belongsTo(AffiliationCorporate::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function ageRange()
    {
        return $this->belongsTo(AgeRange::class);
    }

    public function coverage()
    {
        return $this->belongsTo(Coverage::class);
    }

    
}