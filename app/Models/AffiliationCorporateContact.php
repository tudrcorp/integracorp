<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliationCorporateContact extends Model
{
    /** @use HasFactory<\Database\Factories\AffiliationCorporateContactFactory> */
    use HasFactory;

    protected $table = 'affiliation_corporate_contacts';

    protected $fillable = [
        'affiliation_corporate_id',
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
     * @return BelongsTo<AffiliationCorporate, $this>
     */
    public function affiliationCorporate(): BelongsTo
    {
        return $this->belongsTo(AffiliationCorporate::class);
    }
}
