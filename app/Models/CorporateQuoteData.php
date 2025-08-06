<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorporateQuoteData extends Model
{
    protected $table = 'corporate_quote_data';
    
    protected $fillable = [
        'corporate_quote_id',
        'last_name',
        'first_name',
        'nro_identificacion',
        'birth_date',
        'age',
        'sex',
        'phone',
        'email',
        'condition_medical',
        'initial_date',
        'position_company',
        'address',
        'full_name_emergency',
        'phone_emergency',
    ];

    public function corporateQuote(): BelongsTo
    {
        return $this->belongsTo(CorporateQuote::class, 'id', 'corporate_quote_id');
    }

    
}