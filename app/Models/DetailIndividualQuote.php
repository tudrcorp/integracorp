<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DetailIndividualQuote extends Model
{
    protected $table = 'detail_individual_quotes';
    
    protected $fillable = [
        'individual_quote_id',
        'plan_id',
        'age_range_id',
        'coverage_id',
        'total_persons',
        'subtotal_anual',
        'subtotal_quarterly',
        'subtotal_biannual',
        'subtotal_monthly',
        'details_quote',
        'status',
        'created_by',
        'fee'

    ];

    protected $casts = [
        'details_quote' => 'array', // Convierte automáticamente el JSON a un array en PHP
    ];

    /**
     * Get the user that owns the DetailIndividualQuote
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function individualQuote(): BelongsTo
    {
        return $this->belongsTo(IndividualQuote::class, 'individual_quote_id', 'id');
    }

    /**
     * Get the user that owns the DetailIndividualQuote
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'id');
    }

    /**
     * Get the user that owns the DetailIndividualQuote
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function ageRange(): BelongsTo
    {
        return $this->belongsTo(AgeRange::class, 'age_range_id', 'id');
    }

    /**
     * Get the user that owns the DetailIndividualQuote
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function coverage(): BelongsTo
    {
        return $this->belongsTo(Coverage::class, 'coverage_id', 'id');
    }

    

    
}