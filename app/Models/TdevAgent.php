<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TdevAgent extends Model
{
    protected $fillable = [
        'tdev_agency_id',
        'full_name',
        'position',
        'email',
        'phone',
        'birth_date',
        'registered_at',
        'registration_source',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'registered_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(TdevAgency::class, 'tdev_agency_id');
    }
}
