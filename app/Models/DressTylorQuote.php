<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DressTylorQuote extends Model
{
    protected $table = 'dress_tylor_quotes';

    protected $fillable = [
        'agent_id',
        'agency_code',
        'owner_code',
        'total',
        'anual',
        'mensual',
        'trimestral',
        'semestral',
        'status',
        'created_by',
        'updated_by',
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
