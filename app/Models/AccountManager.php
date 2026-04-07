<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountManager extends Model
{
    protected $table = 'account_managers';

    protected $fillable = [
        'user_id',
        'full_name',
        'ci',
        'birth_date',
        'phone',
        'address',
        'email',
        'created_by',
        'updated_by',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Usuario de negocio que registró el account manager (si aplica).
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all of the comments for the Agency
     */
    public function agencies(): HasMany
    {
        return $this->hasMany(Agency::class, 'ownerAccountManagers', 'user_id');
    }

    /**
     * Get all of the comments for the Agency
     */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class, 'ownerAccountManagers', 'user_id');
    }
}
