<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliationIndividualDocument extends Model
{
    protected $table = 'affiliation_individual_documents';

    protected $fillable = [
        'affiliation_id',
        'title',
        'documents',
        'image',
    ];

    public function affiliation()
    {
        return $this->belongsTo(Affiliation::class);
    }
}