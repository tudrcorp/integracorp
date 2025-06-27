<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorporateQuoteRequestData extends Model
{
    protected $table = 'corporate_quote_request_data';

    protected $fillable = [
        'corporate_quote_request_id',
        'full_name',
        'birth_date',
        'age',
    ];

    public function corporateQuoteRequest()
    {
        return $this->belongsTo(CorporateQuoteRequest::class);
    }

    
}