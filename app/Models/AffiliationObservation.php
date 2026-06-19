<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliationObservation extends Model
{
    /** @use HasFactory<\Database\Factories\AffiliationObservationFactory> */
    use HasFactory;

    protected $fillable = [
        'affiliation_id',
        'description',
        'created_by',
    ];

    public function affiliation(): BelongsTo
    {
        return $this->belongsTo(Affiliation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}
