<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Companies\CompanyAssociateRegistrar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyResponsible extends Model
{
    protected $fillable = [
        'company_id',
        'full_name',
        'identity_card',
        'company',
        'phone',
        'email',
        'state_id',
        'zone_id',
        'contract_start_date',
        'contract_end_date',
        'contracted_days',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contract_start_date' => 'date',
            'contract_end_date' => 'date',
            'contracted_days' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function associates(): HasMany
    {
        return $this->hasMany(CompanyAssociate::class);
    }

    public function consumedRegistrationDays(): int
    {
        return CompanyAssociateRegistrar::consumedDaysByResponsible($this);
    }

    public function availableRegistrationDays(): int
    {
        return CompanyAssociateRegistrar::availableDaysForResponsible($this);
    }

    public function hasExhaustedRegistrationDays(): bool
    {
        return CompanyAssociateRegistrar::hasExhaustedRegistrationDays($this);
    }
}
