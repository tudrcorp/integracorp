<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailCorporateQuote extends Model
{
    //detail_corporate_quotes
    protected $table = 'detail_corporate_quotes';

    protected $fillable = [
        'corporate_quote_id',
        'plan_id',
        'age_range_id',
        'coverage_id',
        'fee',
        'total_persons',
        'subtotal_anual',
        'subtotal_quarterly',
        'subtotal_biannual',
        'subtotal_monthly',
        'details_corporate_quote',
        'status',
        'created_by',
    ];

    protected $casts = [
        'details_corporate_quote' => 'array', // Convierte automáticamente el JSON a un array en PHP
    ];

    /**
     * Get the user that owns the DetailIndividualQuote
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function corporateQuote(): BelongsTo
    {
        return $this->belongsTo(CorporateQuote::class, 'corporate_quote_id', 'id');
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