<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusLogAffiliation extends Model
{
    
    protected $table = 'status_log_affiliations';

    protected $fillable = [
        'affiliation_id',
        'action',
        'updated_by',
        'description',
        'observation'
    ];

    public function status_log()
    {
        return $this->belongsTo(Affiliation::class);
    }
    
}