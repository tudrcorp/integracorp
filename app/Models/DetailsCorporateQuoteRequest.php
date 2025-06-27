<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailsCorporateQuoteRequest extends Model
{
    protected $table = 'details_corporate_quote_requests';

    public $timestamps = false;

    protected $fillable = [
        'corporate_quote_request_id',
        'plan_id',
        'total_persons',
        'status',
        'created_by',
    ];

    public function corporateQuoteRequest()
    {
        return $this->belongsTo(CorporateQuoteRequest::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}