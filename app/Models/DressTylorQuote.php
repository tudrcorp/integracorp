<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DressTylorQuote extends Model
{
    protected $table = 'dress_tylor_quotes';

    protected $fillable = [
        'full_name',
        'rifCi',
        'email',
        'planName',
        'agent_id',
        'agency_code',
        'owner_code',
        'status',
        'created_by',
        'updated_by',
        'quote_structure',
    ];

    protected $casts = [
        'quote_structure' => 'array',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function benefits()
    {
        return $this->hasMany(DressTylorBenefit::class);
    }

    public function ageCovegares()
    {
        return $this->hasMany(DressTylorAgeCoverage::class);
    }

    public function upgrades()
    {
        return $this->hasMany(DressTylorUpgrade::class);
    }
}
