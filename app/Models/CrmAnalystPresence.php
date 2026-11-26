<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Estado del analista en la bandeja. Sin fila, cuenta como disponible.
 */
class CrmAnalystPresence extends Model
{
    protected $table = 'crm_analyst_presence';

    protected $fillable = [
        'user_id',
        'status',
        'status_at',
        'seen_at',
    ];

    protected function casts(): array
    {
        return [
            'status_at' => 'datetime',
            'seen_at' => 'datetime',
        ];
    }
}
