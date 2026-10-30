<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sede donde los médicos de TDG hacen guardia.
 */
class MedicalGuardSite extends Model
{
    protected $fillable = [
        'name',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<MedicalGuard, $this>
     */
    public function guards(): HasMany
    {
        return $this->hasMany(MedicalGuard::class);
    }
}
