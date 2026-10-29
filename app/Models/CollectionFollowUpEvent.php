<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Traza de cada marcado o desmarcado del estado de gestión de una cuota.
 *
 * @see \App\Support\Collections\CollectionFollowUp
 */
class CollectionFollowUpEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'collection_follow_up_events';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'collection_id',
        'affiliation_code',
        'collection_invoice_number',
        'previous_status',
        'new_status',
        'user_id',
        'user_name',
        'ip',
        'user_agent',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
