<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DressTylorUpgrade extends Model
{
    protected $table = 'dress_tylor_upgrades';

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
