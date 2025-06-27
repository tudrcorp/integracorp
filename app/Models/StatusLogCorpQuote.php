<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusLogCorpQuote extends Model
{
    protected $table = 'status_log_corp_quotes';

    protected $fillable = [
        'action',
        'corporate_quote_id',
        'created_by',
        'observation',
        'updated_by'
    ];

    public function corporateQuote()
    {
        return $this->belongsTo(CorporateQuote::class);
    }
}