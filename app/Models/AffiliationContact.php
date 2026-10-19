<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliationContact extends Model
{
    /** @use HasFactory<\Database\Factories\AffiliationContactFactory> */
    use HasFactory;

    protected $table = 'affiliation_contacts';

    protected $fillable = [
        'affiliation_id',
        'name',
        'relationship',
        'phone',
        'email',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Affiliation, $this>
     */
    public function affiliation(): BelongsTo
    {
        return $this->belongsTo(Affiliation::class);
    }
}
