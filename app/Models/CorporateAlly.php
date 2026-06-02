<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CorporateAlly extends Model
{
    protected $fillable = [
        'country_id',
        'state_id',
        'city_id',
        'supplier_category',
        'type_agreement',
        'status_agreement',
        'rif',
        'company_name',
        'phone',
        'people_contact',
        'email',
        'social_networks',
        'address',
        'services',
        'payment_term',
        'supplier_payment',
        'local_beneficiary_name',
        'local_beneficiary_rif',
        'local_beneficiary_account_number',
        'local_beneficiary_account_bank',
        'local_beneficiary_account_type',
        'local_beneficiary_phone_pm',
        'local_beneficiary_account_number_mon_inter',
        'local_beneficiary_account_bank_mon_inter',
        'local_beneficiary_account_type_mon_inter',
        'extra_beneficiary_name',
        'extra_beneficiary_ci_rif',
        'extra_beneficiary_account_number',
        'extra_beneficiary_account_bank',
        'extra_beneficiary_account_type',
        'extra_beneficiary_route',
        'extra_beneficiary_zelle',
        'extra_beneficiary_ach',
        'extra_beneficiary_swift',
        'extra_beneficiary_aba',
        'extra_beneficiary_address',
        'status',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function corporateAllyObservacions(): HasMany
    {
        return $this->hasMany(CorporateAllyObservacion::class)->orderBy('created_at', 'desc');
    }
}
