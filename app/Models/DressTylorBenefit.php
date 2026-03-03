<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DressTylorBenefit extends Model
{
    protected $table = 'dress_tylor_benefits';

    protected $fillable = [
        'dress_tylor_quote_id',
        'benefit_id',
        'cost',
    ];

    public function dressTylorQuote()
    {
        return $this->belongsTo(DressTylorQuote::class);
    }

    public function benefit()
    {
        return $this->belongsTo(Benefit::class);
    }
}
