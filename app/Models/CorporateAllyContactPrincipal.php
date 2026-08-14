<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorporateAllyContactPrincipal extends Model
{
    protected $table = 'corporate_ally_contact_principals';

    protected $fillable = [
        'corporate_ally_id',
        'departament',
        'position',
        'name',
        'email',
        'personal_phone',
        'local_phone',
        'extensions',
        'created_by',
        'updated_by',
    ];

    public function corporateAlly(): BelongsTo
    {
        return $this->belongsTo(CorporateAlly::class);
    }
}
