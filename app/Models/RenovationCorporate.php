<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RenovationCorporate extends Model
{
    //
    protected $table = 'renovation_corporates';

    protected $fillable = [
        'affiliation_corporate_id',
        'date_renewal',
        'status',
        'created_by',
        'updated_by',
        'code_affiliation',
        'agent_id',
        'code_agency',
        'owner_code',
        'owner_agent',
        'info_renovation',
    ];

    protected $casts = [
        'info_renovation' => 'array',
    ];

    public function affiliation()
    {
        return $this->belongsTo(AffiliationCorporate::class);
    }

    public function renovation()
    {
        return $this->belongsTo(Renovation::class);
    }

    public function renovationCorporate()
    {
        return $this->belongsTo(RenovationCorporate::class);
    }
}
