<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhiteCompany extends Model
{
    protected $table = 'white_companies';

    protected $fillable = [
        'name',
        'logo',
        'rif',
        'email',
        'phone',
        'address',
        'city_id',
        'state_id',
        'country_id',
        'updated_by',
        'created_by',
        'assigned_credit',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_credit' => 'decimal:2',
        ];
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id', 'id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id', 'id');
    }

    public function creditReconciliations(): HasMany
    {
        return $this->hasMany(CreditReconciliation::class);
    }

    public function consumedAssignedCredit(?int $exceptId = null): float
    {
        if ($this->relationLoaded('creditReconciliations')) {
            return (float) $this->creditReconciliations
                ->when($exceptId !== null, fn ($records) => $records->reject(
                    fn (CreditReconciliation $record): bool => (int) $record->id === $exceptId
                ))
                ->sum('total_to_pay');
        }

        return (float) $this->creditReconciliations()
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->sum('total_to_pay');
    }

    public function remainingAssignedCredit(?int $exceptId = null): float
    {
        return (float) ($this->assigned_credit ?? 0) - $this->consumedAssignedCredit($exceptId);
    }
}
