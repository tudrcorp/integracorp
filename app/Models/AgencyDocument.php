<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgencyDocument extends Model
{
    protected $table = 'agency_documents';

    protected $fillable = [
        'agency_id',
        'title',
        'document',
        'image',
    ];

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }
}