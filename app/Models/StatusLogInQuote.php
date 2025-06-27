<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusLogInQuote extends Model
{
    protected $table = 'status_log_in_quotes';

    protected $fillable = [
        'action',
        'individual_quote_id',
        'updated_by',
        'observation'
    ];

    public function individualQuote()
    {
        return $this->belongsTo(IndividualQuote::class);
    }
}