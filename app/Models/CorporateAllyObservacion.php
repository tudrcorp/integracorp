<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorporateAllyObservacion extends Model
{
    protected $table = 'corporate_ally_observacions';

    protected $fillable = [
        'corporate_ally_id',
        'observation',
        'created_by',
        'updated_by',
    ];

    public function corporateAlly(): BelongsTo
    {
        return $this->belongsTo(CorporateAlly::class);
    }
}
