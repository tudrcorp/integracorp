<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusLogAffiliationCorporate extends Model
{
    protected $table = 'status_log_affiliation_corporates';

    protected $fillable = [
        'affiliation_corporate_id',
        'action',
        'updated_by',
        'description',
        'observation'
    ];

    public function status_log()
    {
        return $this->belongsTo(AffiliationCorporate::class);
    }
}