<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BenefitCoverage extends Model
{
    protected $table = 'benefit_coverages';

    protected $fillable = [
        'plan_id',
        'benefit_id',
        'coverage_id',
        'limit',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function benefit()
    {
        return $this->belongsTo(Benefit::class);
    }

    public function coverage()
    {
        return $this->belongsTo(Coverage::class);
    }
}