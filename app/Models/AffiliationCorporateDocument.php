<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliationCorporateDocument extends Model
{
    protected $fillable = [
        'affiliation_corporate_id',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    public function affiliationCorporate(): BelongsTo
    {
        return $this->belongsTo(AffiliationCorporate::class);
    }
}
