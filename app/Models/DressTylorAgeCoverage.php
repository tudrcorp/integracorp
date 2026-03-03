<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DressTylorAgeCoverage extends Model
{
    protected $table = 'dress_tylor_age_coverages';

    protected $fillable = [
        'dress_tylor_quote_id',
        'age_range_id',
        'coverage_id',
        'poblation',
        'cost',
    ];

    public function dressTylorQuote()
    {
        return $this->belongsTo(DressTylorQuote::class);
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
