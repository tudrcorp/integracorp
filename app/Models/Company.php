<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Company extends Model
{
    protected $fillable = [
        'plan_generator_id',
        'plan_generator_column_key',
        'plan_generator_column_label',
        'payment_frequency',
        'fee_anual',
        'total_amount',
        'name',
        'rif',
        'email',
        'phone',
        'address',
        'created_by',
        'registration_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (Company $company): void {
            if (blank($company->registration_token)) {
                $company->registration_token = (string) Str::uuid();
            }
        });
    }

    public function planGenerator(): BelongsTo
    {
        return $this->belongsTo(PlanGenerator::class);
    }

    public function responsibles(): HasMany
    {
        return $this->hasMany(CompanyResponsible::class);
    }

    public function associates(): HasMany
    {
        return $this->hasMany(CompanyAssociate::class);
    }

    public function paidMemberships(): HasMany
    {
        return $this->hasMany(CompanyPaidMembership::class);
    }
}
