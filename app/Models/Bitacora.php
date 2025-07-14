<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bitacora extends Model
{
    protected $table = 'bitacoras';

    protected $fillable = [
        'individual_quote_id',
        'user_id',
        'accion',
        'detalle',
    ];

    public function individual_quote()
    {
        return $this->belongsTo(IndividualQuote::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}